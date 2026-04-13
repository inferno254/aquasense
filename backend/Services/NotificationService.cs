using AquasenseApi.Models;

namespace AquasenseApi.Services;

public interface INotificationService
{
    Task SendSmsAsync(string? phoneNumber, string message);
}

public class NotificationService : INotificationService
{
    private readonly IConfiguration _configuration;

    public NotificationService(IConfiguration configuration)
    {
        _configuration = configuration;
    }

    public Task SendSmsAsync(string? phoneNumber, string message)
    {
        var smsEnabled = _configuration.GetValue<bool>("SMS:Enabled", false);
        if (!smsEnabled || string.IsNullOrWhiteSpace(phoneNumber))
        {
            Console.WriteLine($"[SMS] Skipped. Enabled={smsEnabled}, Phone={(phoneNumber ?? "none")}.");
            return Task.CompletedTask;
        }

        // SMS gateway placeholder: replace with Twilio or similar in production.
        var smsFrom = _configuration["SMS:FromNumber"] ?? "AquaSense";
        Console.WriteLine($"[SMS] From={smsFrom} To={phoneNumber} Message={message}");
        return Task.CompletedTask;
    }
}
