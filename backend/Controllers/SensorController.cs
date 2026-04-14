using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using AquasenseApi.Data;
using AquasenseApi.Models;
using AquasenseApi.Services;
using System.Security.Claims;

namespace AquasenseApi.Controllers;

[ApiController]
[Authorize]
[Route("api/[controller]")]
public class SensorController : ControllerBase
{
    private readonly AquasenseDbContext _context;
    private readonly IIrrigationService _irrigationService;

    public SensorController(AquasenseDbContext context, IIrrigationService irrigationService)
    {
        _context = context;
        _irrigationService = irrigationService;
    }

    // Hardware webhook - ESP32 anonymous POST
    [AllowAnonymous]
    [HttpPost("hardware/{farmId}/reading")]
    public async Task<ActionResult> HardwareSensorReading(Guid farmId, [FromBody] SensorReadingDto request)
    {
        if (!ModelState.IsValid || !request.SoilMoisture.HasValue)
        {
            return BadRequest("Valid soil moisture required");
        }

        try 
        {
            var reading = new SensorReading
            {
                ReadingId = Guid.NewGuid(),
                FarmId = farmId,
                SoilMoisture = request.SoilMoisture.Value,
                Temperature = request.Temperature,
                Humidity = request.Humidity,
                Timestamp = request.Timestamp ?? DateTime.UtcNow
            };

            _context.SensorReadings.Add(reading);
            await _context.SaveChangesAsync();

            // Trigger irrigation logic/SMS if low
            await _irrigationService.ProcessSensorReadingAsync(farmId, reading);

            // Response for ESP32 firmware (match existing "alert" format)
            var lowMoisture = request.SoilMoisture < 35;
            return Ok(new 
            {
                message = "Sensor reading recorded",
                readingId = reading.ReadingId,
                alert = lowMoisture
            });
        }
        catch (Exception ex)
        {
            return StatusCode(500, new { error = ex.Message });
        }
    }

    [HttpPost("farms/{farmId}/sensor-readings")]
    public async Task<ActionResult> AddSensorReading(Guid farmId, [FromBody] SensorReadingDto request)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        var reading = new SensorReading
        {
            ReadingId = Guid.NewGuid(),
            FarmId = farmId,
            SoilMoisture = request.SoilMoisture,
            Temperature = request.Temperature,
            Humidity = request.Humidity,
            Timestamp = request.Timestamp ?? DateTime.UtcNow
        };

        _context.SensorReadings.Add(reading);
        await _context.SaveChangesAsync();

        await _irrigationService.ProcessSensorReadingAsync(farmId, reading);

        return Ok(new
        {
            message = "Sensor reading recorded",
            readingId = reading.ReadingId,
            alert = request.SoilMoisture.HasValue && request.SoilMoisture.Value < 35m
        });
    }

    private Guid GetUserId()
    {
        var idClaim = User.FindFirstValue("id");
        return Guid.TryParse(idClaim, out var userId) ? userId : Guid.Empty;
    }

    private async Task<bool> HasFarmAccess(Guid farmId)
    {
        var role = User.FindFirstValue(ClaimTypes.Role);
        if (role == "admin")
        {
            return true;
        }

        var userId = GetUserId();
        return await _context.Farms.AnyAsync(f => f.FarmId == farmId && f.UserId == userId);
    }
}

public class SensorReadingDto
{
    public decimal? SoilMoisture { get; set; }
    public decimal? Temperature { get; set; }
    public decimal? Humidity { get; set; }
    public DateTime? Timestamp { get; set; }
}
