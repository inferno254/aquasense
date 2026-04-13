using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.IdentityModel.Tokens;
using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using System.Text;
using AquasenseApi.Data;
using AquasenseApi.Models;

namespace AquasenseApi.Controllers;

[ApiController]
[Route("api/[controller]")]
public class AuthController : ControllerBase
{
    private readonly AquasenseDbContext _context;
    private readonly IConfiguration _configuration;

    public AuthController(AquasenseDbContext context, IConfiguration configuration)
    {
        _context = context;
        _configuration = configuration;
    }
    
    [HttpPost("login")]
    public async Task<ActionResult> Login([FromBody] LoginDto loginDto)
    {
        var user = await _context.Users
            .Include(u => u.Farms)
            .FirstOrDefaultAsync(u => u.Email == loginDto.Email);
            
        if (user == null || !BCrypt.Net.BCrypt.Verify(loginDto.Password, user.PasswordHash))
        {
            return Unauthorized(new { message = "Invalid credentials" });
        }

        var token = GenerateJwtToken(user);
        var farms = user.Farms.Select(f => new
        {
            f.FarmId,
            f.Location,
            f.CropType,
            f.Size
        }).ToList();

        return Ok(new
        {
            token,
            user = new { user.UserId, user.Name, user.Email, user.Role },
            farms,
            message = "Login successful"
        });
    }

    [HttpPost("register")]
    public async Task<ActionResult> Register([FromBody] RegisterDto registerDto)
    {
        if (string.IsNullOrWhiteSpace(registerDto.Email) || string.IsNullOrWhiteSpace(registerDto.Password) || string.IsNullOrWhiteSpace(registerDto.Name))
        {
            return BadRequest(new { message = "Name, email, and password are required." });
        }

        if (await _context.Users.AnyAsync(u => u.Email == registerDto.Email))
        {
            return Conflict(new { message = "An account with that email already exists." });
        }

        var newUser = new User
        {
            UserId = Guid.NewGuid(),
            Name = registerDto.Name,
            Email = registerDto.Email,
            PasswordHash = BCrypt.Net.BCrypt.HashPassword(registerDto.Password),
            Role = "farmer"
        };

        var farm = new Farm
        {
            FarmId = Guid.NewGuid(),
            UserId = newUser.UserId,
            Location = "New Farm",
            Size = 1.0m,
            CropType = "Maize"
        };

        _context.Users.Add(newUser);
        _context.Farms.Add(farm);
        await _context.SaveChangesAsync();

        var token = GenerateJwtToken(newUser);
        return Ok(new
        {
            token,
            user = new { newUser.UserId, newUser.Name, newUser.Email, newUser.Role },
            farms = new[] { new { farm.FarmId, farm.Location, farm.CropType, farm.Size } },
            message = "Registration successful"
        });
    }

    private string GenerateJwtToken(User user)
    {
        var claims = new[]
        {
            new Claim("id", user.UserId.ToString()),
            new Claim(ClaimTypes.Email, user.Email),
            new Claim(ClaimTypes.Name, user.Name),
            new Claim(ClaimTypes.Role, user.Role)
        };

        var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(_configuration["JWT:Key"]!));
        var creds = new SigningCredentials(key, SecurityAlgorithms.HmacSha256);

        var token = new JwtSecurityToken(
            issuer: _configuration["JWT:Issuer"],
            audience: _configuration["JWT:Audience"],
            claims: claims,
            expires: DateTime.UtcNow.AddHours(8),
            signingCredentials: creds
        );

        return new JwtSecurityTokenHandler().WriteToken(token);
    }
}
