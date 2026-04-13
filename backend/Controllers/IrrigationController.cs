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
public class IrrigationController : ControllerBase
{
    private readonly AquasenseDbContext _context;

    public IrrigationController(AquasenseDbContext context)
    {
        _context = context;
    }

    [HttpPost("farms/{farmId}/irrigate")]
    public async Task<ActionResult> TriggerIrrigate(Guid farmId, [FromBody] IrrigateRequest request)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        // TODO: In production, send signal to ESP32 via MQTT/Webhook
        var @event = new IrrigationEvent
        {
            FarmId = farmId,
            TriggerType = request.Manual ? "manual" : "automatic",
            StartTime = DateTime.UtcNow,
            WaterUsed = request.EstimatedLitres,
            Status = "active"
        };

        _context.IrrigationEvents.Add(@event);

        if (request.Manual)
        {
            var alert = new SystemAlert
            {
                FarmId = farmId,
                AlertType = "manual_irrigation",
                Message = $"Manual irrigation started: {request.Duration}min, {request.EstimatedLitres}L"
            };
            _context.SystemAlerts.Add(alert);
        }

        await _context.SaveChangesAsync();

        return Ok(new
        {
            message = "Irrigation event logged",
            eventId = @event.EventId
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

public class IrrigateRequest
{
    public bool Manual { get; set; } = true;
    public int Duration { get; set; } = 10;
    public decimal EstimatedLitres { get; set; } = 500;
}
