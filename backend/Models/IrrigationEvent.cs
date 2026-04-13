using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace AquasenseApi.Models;

public class IrrigationEvent
{
    [Key]
    public Guid EventId { get; set; }
    
    public Guid FarmId { get; set; }
    
    [MaxLength(20)]
    public string TriggerType { get; set; } = "automatic";  // automatic/manual
    
    public DateTime StartTime { get; set; } = DateTime.UtcNow;
    
    public DateTime? EndTime { get; set; }
    
    public decimal? WaterUsed { get; set; }  // litres
    
    [MaxLength(20)]
    public string Status { get; set; } = "completed";
    
    [ForeignKey("FarmId")]
    public virtual Farm Farm { get; set; } = null!;
}
