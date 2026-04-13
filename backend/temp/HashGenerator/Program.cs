using BCrypt.Net;

Console.WriteLine("Password hashes:");
Console.WriteLine($"password123: {BCrypt.Net.BCrypt.HashPassword("password123")}");
Console.WriteLine($"irrigate2024: {BCrypt.Net.BCrypt.HashPassword("irrigate2024")}");
