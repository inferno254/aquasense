using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace AquasenseApi.Models;

public class Farm
{
    [Key]
    public Guid FarmId { get; set; }
    
    public Guid UserId { get; set; }
    
    [Required, MaxLength(200)]
    public string Location { get; set; } = string.Empty;
    
    public decimal? Size { get; set; }  // hectares
    
    [MaxLength(50)]
    public string? CropType { get; set; }
    
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    
    [ForeignKey("UserId")]
    public virtual User User { get; set; } = null!;
    
    public virtual ICollection<SensorReading> SensorReadings { get; set; } = new List<SensorReading>();
    public virtual ICollection<IrrigationEvent> IrrigationEvents { get; set; } = new List<IrrigationEvent>();
    public virtual ICollection<SystemAlert> SystemAlerts { get; set; } = new List<SystemAlert>();
}
