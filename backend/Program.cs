using Microsoft.EntityFrameworkCore;
using Microsoft.AspNetCore.Authentication.JwtBearer;
using Microsoft.IdentityModel.Tokens;
using Npgsql;
using System.Text;
using AquasenseApi.Data;

var builder = WebApplication.CreateBuilder(args);

// Add services
builder.Services.AddControllers();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// Database
var connectionString = Environment.GetEnvironmentVariable("DB_CONNECTION")?.Trim();
if (string.IsNullOrEmpty(connectionString))
{
    connectionString = builder.Configuration.GetConnectionString("DefaultConnection");
}

var dbPassword = Environment.GetEnvironmentVariable("DB_PASSWORD");
if (!string.IsNullOrWhiteSpace(dbPassword) && !string.IsNullOrWhiteSpace(connectionString))
{
    try
    {
        var connectionStringBuilder = new NpgsqlConnectionStringBuilder(connectionString)
        {
            Password = dbPassword
        };
        connectionString = connectionStringBuilder.ToString();
    }
    catch
    {
        // invalid PostgreSQL connection string; fallback to SQLite
    }
}

var useSqlite = string.IsNullOrWhiteSpace(connectionString)
    || connectionString.Contains("YOUR_POSTGRES_PASSWORD_HERE", StringComparison.OrdinalIgnoreCase)
    || (connectionString.Contains("Password=", StringComparison.OrdinalIgnoreCase)
        && connectionString.Trim().EndsWith("Password=", StringComparison.OrdinalIgnoreCase));

if (useSqlite)
{
    var sqliteDbPath = System.IO.Path.Combine(builder.Environment.ContentRootPath, "aquasense.db");
    builder.Services.AddDbContext<AquasenseDbContext>(options =>
        options.UseSqlite($"Data Source={sqliteDbPath}"));
}
else
{
    builder.Services.AddDbContext<AquasenseDbContext>(options =>
        options.UseNpgsql(connectionString));
}

// JWT authentication
var jwtKey = builder.Configuration["JWT:Key"];
var jwtIssuer = builder.Configuration["JWT:Issuer"];
var jwtAudience = builder.Configuration["JWT:Audience"];

if (string.IsNullOrWhiteSpace(jwtKey) || string.IsNullOrWhiteSpace(jwtIssuer) || string.IsNullOrWhiteSpace(jwtAudience))
{
    throw new InvalidOperationException("JWT configuration is required in appsettings.json.");
}

builder.Services.AddAuthentication(options =>
{
    options.DefaultAuthenticateScheme = JwtBearerDefaults.AuthenticationScheme;
    options.DefaultChallengeScheme = JwtBearerDefaults.AuthenticationScheme;
})
.AddJwtBearer(options =>
{
    options.RequireHttpsMetadata = false;
    options.SaveToken = true;
    options.TokenValidationParameters = new TokenValidationParameters
    {
        ValidateIssuerSigningKey = true,
        IssuerSigningKey = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(jwtKey)),
        ValidateIssuer = true,
        ValidIssuer = jwtIssuer,
        ValidateAudience = true,
        ValidAudience = jwtAudience,
        ValidateLifetime = true,
        ClockSkew = TimeSpan.FromMinutes(2)
    };
});

builder.Services.AddAuthorization();

builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAll", policy =>
        policy.AllowAnyOrigin().AllowAnyMethod().AllowAnyHeader());
});

// Scoped registration for controllers
builder.Services.AddScoped<AquasenseDbContext>();

var app = builder.Build();

using (var scope = app.Services.CreateScope())
{
    var services = scope.ServiceProvider;
    await DbInitializer.SeedAsync(services);
}

// Configure pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseCors("AllowAll");
app.UseHttpsRedirection();
app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();

app.Run("http://localhost:5000");
