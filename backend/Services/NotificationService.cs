using AfricasTalking;
using AquasenseApi.Models;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace AquasenseApi.Services;

public interface INotificationService
{
    Task SendSmsAsync(string? phoneNumber, string message);
}

public class NotificationService : INotificationService
{
    private readonly IConfiguration _configuration;
    private readonly ILogger<NotificationService> _logger;
    private readonly AfricasTalking<IAfricasTalking> _smsService;
    private readonly string _username;
    private readonly string _apiKey;

    public NotificationService(IConfiguration configuration, ILogger<NotificationService> logger)
    {
        _configuration = configuration;
        _logger = logger;

        _username = _configuration["SMS:AfricasTalking:Username"] ?? "";
        _apiKey = _configuration["SMS:AfricasTalking:ApiKey"] ?? "";
        
        var credentials = new AfricasTalkingCredentials
        {
            Username = _username,
            ApiKey = _apiKey
        };
        
        var initializer = new AfricasTalkingInitializer(credentials);
        _smsService = AfricasTalking<IAfricasTalking>.Initialize(initializer);
    }

    public async Task SendSmsAsync(string? phoneNumber, string message)
    {
        var smsEnabled = _configuration.GetValue<bool>("SMS:Enabled", false);
        if (!smsEnabled || string.IsNullOrWhiteSpace(phoneNumber) || string.IsNullOrEmpty(_username) || string.IsNullOrEmpty(_apiKey))
        {
            _logger.LogWarning($"[SMS] Skipped. Enabled={smsEnabled}, Phone={phoneNumber ?? "none"}, Configured={(!string.IsNullOrEmpty(_username))}");
            return;
        }

        try
        {
            // Format Kenyan number: +2547XXXXXXXX
            var formattedNumber = phoneNumber?.StartsWith("+254") == true ? phoneNumber : $"+254{phoneNumber?[1..]}";
            
            var smsRequest = new SMSRequest
            {
                To = formattedNumber,
                Message = $"🌱 AquaSense: {message.Substring(0, Math.Min(140, message.Length))}".TrimEnd(),
                From = _configuration["SMS:FromNumber"] ?? "AquaSense"
            };

            var response = await _smsService.SMS.SendAsync(smsRequest);
            
            _logger.LogInformation($"[SMS] Sent to {formattedNumber}. SID={response?.SmsBundleId}, Count={response?.Responses?.Length ?? 0}");
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, $"[SMS] Failed to send to {phoneNumber}: {ex.Message}");
        }
    }
}
