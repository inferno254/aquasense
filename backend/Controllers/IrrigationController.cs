using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using System.Security.Claims;
using AquasenseApi.Data;
using AquasenseApi.Models;
using AquasenseApi.Services;

namespace AquasenseApi.Controllers;

[ApiController]
[Authorize]
[Route("api/[controller]")]
public class IrrigationController : ControllerBase
{
    private readonly AquasenseDbContext _context;
    private readonly IIrrigationService _irrigationService;

    public IrrigationController(AquasenseDbContext context, IIrrigationService irrigationService)
    {
        _context = context;
        _irrigationService = irrigationService;
    }

    [HttpPost("farms/{farmId}/irrigate")]
    public async Task<ActionResult> TriggerIrrigate(Guid farmId, [FromBody] IrrigateRequest request)
    {
        if (!await HasFarmAccess(farmId))
        {
            return Forbid();
        }

        var irrigationEvent = await _irrigationService.TriggerIrrigationAsync(
            farmId,
            request.Manual,
            request.Duration,
            request.EstimatedLitres);

        return Ok(new
        {
            message = "Irrigation event created",
            eventId = irrigationEvent.EventId,
            triggerType = irrigationEvent.TriggerType,
            startedAt = irrigationEvent.StartTime
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
