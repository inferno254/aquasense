using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace AquasenseApi.Models;

public class SensorReading
{
    [Key]
    public Guid ReadingId { get; set; }
    
    public Guid FarmId { get; set; }
    
    public decimal? SoilMoisture { get; set; }
    
    public decimal? Temperature { get; set; }
    
    public decimal? Humidity { get; set; }
    
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;
    
    [ForeignKey("FarmId")]
    public virtual Farm Farm { get; set; } = null!;
}
