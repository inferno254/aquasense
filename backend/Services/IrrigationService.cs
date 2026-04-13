using System.Net.Http.Json;
using AquasenseApi.Data;
using AquasenseApi.Models;

namespace AquasenseApi.Services;

public interface IIrrigationService
{
    Task<IrrigationEvent> TriggerIrrigationAsync(Guid farmId, bool manual, int duration, decimal estimatedLitres);
    Task ProcessSensorReadingAsync(Guid farmId, SensorReading reading);
}

public class IrrigationService : IIrrigationService
{
    private readonly AquasenseDbContext _context;
    private readonly INotificationService _notificationService;
    private readonly HttpClient _httpClient;
    private readonly IConfiguration _configuration;
    private readonly decimal _autoThreshold;
    private readonly int _defaultDuration;
    private readonly string? _controllerWebhookUrl;

    public IrrigationService(
        AquasenseDbContext context,
        INotificationService notificationService,
        IHttpClientFactory httpClientFactory,
        IConfiguration configuration)
    {
        _context = context;
        _notificationService = notificationService;
        _httpClient = httpClientFactory.CreateClient();
        _configuration = configuration;
        _autoThreshold = _configuration.GetValue<decimal>("Irrigation:AutoThreshold", 35m);
        _defaultDuration = _configuration.GetValue<int>("Irrigation:DefaultDurationMinutes", 12);
        _controllerWebhookUrl = _configuration["Irrigation:ControllerWebhookUrl"];
    }

    public async Task<IrrigationEvent> TriggerIrrigationAsync(Guid farmId, bool manual, int duration, decimal estimatedLitres)
    {
        var farm = await _context.Farms.Include(f => f.User).FirstOrDefaultAsync(f => f.FarmId == farmId);
        if (farm == null)
        {
            throw new InvalidOperationException("Farm not found.");
        }

        var irrigationEvent = new IrrigationEvent
        {
            FarmId = farmId,
            TriggerType = manual ? "manual" : "automatic",
            StartTime = DateTime.UtcNow,
            EndTime = DateTime.UtcNow.AddMinutes(duration),
            WaterUsed = estimatedLitres,
            Status = "active"
        };

        _context.IrrigationEvents.Add(irrigationEvent);

        var alert = new SystemAlert
        {
            FarmId = farmId,
            AlertType = manual ? "manual_irrigation" : "automatic_irrigation",
            Message = manual
                ? $"Manual irrigation started: {duration} min, {estimatedLitres} L"
                : $"Automatic irrigation started: {duration} min, {estimatedLitres} L",
            Timestamp = DateTime.UtcNow
        };

        _context.SystemAlerts.Add(alert);
        await _context.SaveChangesAsync();

        await SendIrrigationControlSignalAsync(farm, irrigationEvent);
        await _notificationService.SendSmsAsync(farm.User.Phone, GetIrrigationSmsMessage(farm, irrigationEvent, manual));

        return irrigationEvent;
    }

    public async Task ProcessSensorReadingAsync(Guid farmId, SensorReading reading)
    {
        var farm = await _context.Farms.Include(f => f.User).FirstOrDefaultAsync(f => f.FarmId == farmId);
        if (farm == null || !reading.SoilMoisture.HasValue)
        {
            return;
        }

        var moisture = reading.SoilMoisture.Value;
        if (moisture >= _autoThreshold)
        {
            return;
        }

        var recentAuto = await _context.IrrigationEvents
            .AnyAsync(e => e.FarmId == farmId && e.TriggerType == "automatic" && e.StartTime >= DateTime.UtcNow.AddMinutes(-30));

        if (recentAuto)
        {
            return;
        }

        var estimatedLitres = Math.Clamp(320m + (_autoThreshold - moisture) * 12m, 220m, 650m);
        var irrigationEvent = new IrrigationEvent
        {
            FarmId = farmId,
            TriggerType = "automatic",
            StartTime = DateTime.UtcNow,
            EndTime = DateTime.UtcNow.AddMinutes(_defaultDuration),
            WaterUsed = estimatedLitres,
            Status = "active"
        };

        var alert = new SystemAlert
        {
            FarmId = farmId,
            AlertType = "low_moisture",
            Message = $"Soil moisture dropped to {moisture:F1}%. Automatic irrigation triggered.",
            Timestamp = DateTime.UtcNow
        };

        _context.IrrigationEvents.Add(irrigationEvent);
        _context.SystemAlerts.Add(alert);
        await _context.SaveChangesAsync();

        await SendIrrigationControlSignalAsync(farm, irrigationEvent);
        await _notificationService.SendSmsAsync(farm.User.Phone, $"AquaSense alert: automatic irrigation started for {farm.Location} after soil moisture fell to {moisture:F1}%.");
    }

    private async Task SendIrrigationControlSignalAsync(Farm farm, IrrigationEvent irrigationEvent)
    {
        if (string.IsNullOrWhiteSpace(_controllerWebhookUrl))
        {
            Console.WriteLine($"[IrrigationControl] No controller webhook configured for farm '{farm.Location}'. EventId={irrigationEvent.EventId}");
            return;
        }

        var payload = new
        {
            farmId = farm.FarmId,
            eventId = irrigationEvent.EventId,
            triggerType = irrigationEvent.TriggerType,
            durationMinutes = (irrigationEvent.EndTime.HasValue ? (int)(irrigationEvent.EndTime.Value - irrigationEvent.StartTime).TotalMinutes : _defaultDuration),
            litres = irrigationEvent.WaterUsed
        };

        try
        {
            var response = await _httpClient.PostAsJsonAsync(_controllerWebhookUrl, payload);
            Console.WriteLine($"[IrrigationControl] Sent webhook to {_controllerWebhookUrl}. StatusCode={response.StatusCode}");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"[IrrigationControl] Webhook send failed: {ex.Message}");
        }
    }

    private string GetIrrigationSmsMessage(Farm farm, IrrigationEvent irrigationEvent, bool manual)
    {
        var duration = irrigationEvent.EndTime.HasValue
            ? (int)(irrigationEvent.EndTime.Value - irrigationEvent.StartTime).TotalMinutes
            : _defaultDuration;

        return manual
            ? $"AquaSense: Manual irrigation started for {farm.Location}. Duration {duration} min."
            : $"AquaSense: Automatic irrigation started for {farm.Location}. Moisture below threshold.";
    }
}
