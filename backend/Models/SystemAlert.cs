using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace AquasenseApi.Models;

public class SystemAlert
{
    [Key]
    public Guid AlertId { get; set; }
    
    public Guid FarmId { get; set; }
    
    [MaxLength(50)]
    public string AlertType { get; set; } = string.Empty;  // low_moisture, sensor_fault, etc.
    
    public string? Message { get; set; }
    
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;
    
    public bool Resolved { get; set; } = false;
    
    public DateTime? ResolvedAt { get; set; }
    
    [ForeignKey("FarmId")]
    public virtual Farm Farm { get; set; } = null!;
}
