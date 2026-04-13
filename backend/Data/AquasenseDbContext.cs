using Microsoft.EntityFrameworkCore;
using AquasenseApi.Models;

namespace AquasenseApi.Data;

public class AquasenseDbContext : DbContext
{
    public AquasenseDbContext(DbContextOptions<AquasenseDbContext> options) : base(options) { }
    
    public DbSet<User> Users { get; set; }
    public DbSet<Farm> Farms { get; set; }
    public DbSet<SensorReading> SensorReadings { get; set; }
    public DbSet<IrrigationEvent> IrrigationEvents { get; set; }
    public DbSet<SystemAlert> SystemAlerts { get; set; }
    
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        // User
        modelBuilder.Entity<User>(entity =>
        {
            entity.HasKey(e => e.UserId);
            entity.Property(e => e.Email).IsRequired().HasMaxLength(100);
            entity.HasIndex(e => e.Email).IsUnique();
        });
        
        // Farm
        modelBuilder.Entity<Farm>(entity =>
        {
            entity.HasKey(e => e.FarmId);
            entity.HasOne(e => e.User)
                .WithMany(u => u.Farms)
                .HasForeignKey(e => e.UserId)
                .OnDelete(DeleteBehavior.Cascade);
        });
        
        // SensorReading (Timescale-like, but EF Core)
        modelBuilder.Entity<SensorReading>(entity =>
        {
            entity.HasKey(e => e.ReadingId);
            entity.HasOne(e => e.Farm)
                .WithMany(f => f.SensorReadings)
                .HasForeignKey(e => e.FarmId)
                .OnDelete(DeleteBehavior.Cascade);
            entity.HasIndex(s => new { s.FarmId, s.Timestamp });
        });
        
        // IrrigationEvent
        modelBuilder.Entity<IrrigationEvent>(entity =>
        {
            entity.HasKey(e => e.EventId);
            entity.HasOne(e => e.Farm)
                .WithMany(f => f.IrrigationEvents)
                .HasForeignKey(e => e.FarmId)
                .OnDelete(DeleteBehavior.Cascade);
        });
        
        // SystemAlert
        modelBuilder.Entity<SystemAlert>(entity =>
        {
            entity.HasKey(e => e.AlertId);
            entity.HasOne(e => e.Farm)
                .WithMany(f => f.SystemAlerts)
                .HasForeignKey(e => e.FarmId)
                .OnDelete(DeleteBehavior.Cascade);
        });
    }
}
