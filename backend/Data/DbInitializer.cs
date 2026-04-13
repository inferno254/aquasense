using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using AquasenseApi.Models;

namespace AquasenseApi.Data;

public static class DbInitializer
{
    public static async Task SeedAsync(IServiceProvider serviceProvider)
    {
        var context = serviceProvider.GetRequiredService<AquasenseDbContext>();
        await context.Database.EnsureCreatedAsync();

        if (await context.Users.AnyAsync())
        {
            return;
        }

        var adminUser = new User
        {
            UserId = Guid.Parse("22222222-2222-2222-2222-222222222222"),
            Name = "Aqua Admin",
            Email = "admin@example.com",
            PasswordHash = BCrypt.Net.BCrypt.HashPassword("password123"),
            Role = "admin"
        };

        var farmerUser = new User
        {
            UserId = Guid.Parse("11111111-1111-1111-1111-111111111111"),
            Name = "Aqua Farmer",
            Email = "farmer@kenya.com",
            PasswordHash = BCrypt.Net.BCrypt.HashPassword("irrigate2024"),
            Role = "farmer"
        };

        var farm = new Farm
        {
            FarmId = Guid.Parse("550e8400-e29b-41d4-a716-446655440002"),
            UserId = farmerUser.UserId,
            Location = "Ngong Hills Farm, Kenya",
            Size = 1.5m,
            CropType = "Maize"
        };

        await context.Users.AddRangeAsync(adminUser, farmerUser);
        await context.Farms.AddAsync(farm);

        var now = DateTime.UtcNow;
        var readings = Enumerable.Range(0, 18).Select(index => new SensorReading
        {
            ReadingId = Guid.NewGuid(),
            FarmId = farm.FarmId,
            SoilMoisture = 25 + index * 0.8m + (decimal)(new Random().NextDouble() * 2.0),
            Temperature = 24 + index * 0.1m + (decimal)(new Random().NextDouble() * 1.5),
            Humidity = 60 + index * 0.4m + (decimal)(new Random().NextDouble() * 2.5),
            Timestamp = now.AddMinutes(-index * 30)
        });

        await context.SensorReadings.AddRangeAsync(readings);

        await context.IrrigationEvents.AddRangeAsync(
            new IrrigationEvent
            {
                EventId = Guid.NewGuid(),
                FarmId = farm.FarmId,
                TriggerType = "automatic",
                StartTime = now.AddHours(-5),
                EndTime = now.AddHours(-5).AddMinutes(12),
                WaterUsed = 420,
                Status = "completed"
            },
            new IrrigationEvent
            {
                EventId = Guid.NewGuid(),
                FarmId = farm.FarmId,
                TriggerType = "manual",
                StartTime = now.AddHours(-2),
                EndTime = now.AddHours(-2).AddMinutes(10),
                WaterUsed = 350,
                Status = "completed"
            }
        );

        await context.SystemAlerts.AddRangeAsync(
            new SystemAlert
            {
                AlertId = Guid.NewGuid(),
                FarmId = farm.FarmId,
                AlertType = "low_moisture",
                Message = "Soil moisture dropped below the threshold.",
                Timestamp = now.AddHours(-1),
                Resolved = false
            },
            new SystemAlert
            {
                AlertId = Guid.NewGuid(),
                FarmId = farm.FarmId,
                AlertType = "sensor_check",
                Message = "Sensor signal restored successfully.",
                Timestamp = now.AddHours(-3),
                Resolved = true,
                ResolvedAt = now.AddHours(-2.5)
            }
        );

        await context.SaveChangesAsync();
    }
}
