using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Authorization;
using Microsoft.EntityFrameworkCore;
using System.Security.Claims;
using AquasenseApi.Data;
using AquasenseApi.Models;

namespace AquasenseApi.Controllers;

[ApiController]
[Authorize]
[Route("api/[controller]")]
public class DataController : ControllerBase
{
    private readonly AquasenseDbContext _context;

    public DataController(AquasenseDbContext context)
    {
        _context = context;
    }

    [HttpGet("farms")]
    public async Task<ActionResult> GetFarms()
    {
        var userId = GetUserId();
        var role = User.FindFirstValue(ClaimTypes.Role);

        var query = _context.Farms.AsQueryable();
        if (role != "admin")
        {
            query = query.Where(f => f.UserId == userId);
        }

        var farms = await query
            .Select(f => new { f.FarmId, f.Location, f.CropType, f.Size })
            .ToListAsync();

        return Ok(farms);
    }

    [HttpGet("farms/{farmId}/sensors")]
    public async Task<ActionResult> GetRecentSensors(Guid farmId, [FromQuery] int hours = 24)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        var fromTime = DateTime.UtcNow.AddHours(-hours);

        var readings = await _context.SensorReadings
            .Where(r => r.FarmId == farmId && r.Timestamp >= fromTime)
            .OrderByDescending(r => r.Timestamp)
            .Take(100)
            .Select(r => new {
                r.Timestamp,
                r.SoilMoisture,
                r.Temperature,
                r.Humidity
            })
            .ToListAsync();

        return Ok(new { latest = readings.FirstOrDefault(), all = readings });
    }

    [HttpGet("farms/{farmId}/events")]
    public async Task<ActionResult> GetRecentEvents(Guid farmId, [FromQuery] int limit = 10)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        var events = await _context.IrrigationEvents
            .Where(e => e.FarmId == farmId)
            .OrderByDescending(e => e.StartTime)
            .Take(limit)
            .Select(e => new {
                e.EventId,
                e.TriggerType,
                e.StartTime,
                Duration = e.EndTime.HasValue ? (int?)(e.EndTime.Value - e.StartTime).TotalMinutes : null,
                e.WaterUsed,
                e.Status
            })
            .ToListAsync();

        return Ok(events);
    }

    [HttpGet("farms/{farmId}/alerts")]
    public async Task<ActionResult> GetAlerts(Guid farmId, [FromQuery] bool? resolved = null)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        var query = _context.SystemAlerts.Where(a => a.FarmId == farmId);

        if (resolved.HasValue)
        {
            query = query.Where(a => a.Resolved == resolved.Value);
        }

        var alerts = await query
            .OrderByDescending(a => a.Timestamp)
            .Take(20)
            .Select(a => new {
                a.AlertId,
                a.AlertType,
                a.Message,
                a.Timestamp,
                a.Resolved
            })
            .ToListAsync();

        return Ok(alerts);
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
