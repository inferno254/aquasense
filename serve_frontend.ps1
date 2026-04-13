param(
    [string]$Root = "$PSScriptRoot",
    [string]$Url = 'http://localhost:8080/'
)

Add-Type -Language CSharp -TypeDefinition @'
using System;
using System.Net;
using System.IO;
using System.Text;

public class SimpleHttpServer
{
    private readonly HttpListener _listener;
    private readonly string _root;
    public SimpleHttpServer(string prefix, string root)
    {
        _listener = new HttpListener();
        _listener.Prefixes.Add(prefix);
        _root = root;
    }

    public void Start()
    {
        _listener.Start();
        Console.WriteLine("Serving HTTP on {0} from {1}", string.Join(",", _listener.Prefixes), _root);
        while (true)
        {
            var context = _listener.GetContext();
            try
            {
                var requestPath = context.Request.Url.AbsolutePath;
                if (requestPath == "/") requestPath = "/index.html";
                var filePath = Path.Combine(_root, requestPath.TrimStart('/').Replace('/', Path.DirectorySeparatorChar));
                if (!File.Exists(filePath))
                {
                    context.Response.StatusCode = 404;
                    var message = Encoding.UTF8.GetBytes("404 - Not Found");
                    context.Response.OutputStream.Write(message, 0, message.Length);
                    context.Response.Close();
                    continue;
                }
                var content = File.ReadAllBytes(filePath);
                context.Response.ContentType = GetContentType(Path.GetExtension(filePath));
                context.Response.ContentLength64 = content.Length;
                context.Response.OutputStream.Write(content, 0, content.Length);
                context.Response.Close();
            }
            catch (Exception ex)
            {
                var error = Encoding.UTF8.GetBytes(ex.ToString());
                context.Response.StatusCode = 500;
                context.Response.OutputStream.Write(error, 0, error.Length);
                context.Response.Close();
            }
        }
    }

    private static string GetContentType(string ext)
    {
        ext = ext.ToLower();
        if (ext == ".html") return "text/html";
        if (ext == ".js") return "application/javascript";
        if (ext == ".css") return "text/css";
        if (ext == ".json") return "application/json";
        if (ext == ".png") return "image/png";
        if (ext == ".jpg" || ext == ".jpeg") return "image/jpeg";
        if (ext == ".svg") return "image/svg+xml";
        return "application/octet-stream";
    }
}
'@

$server = [SimpleHttpServer]::new($Url, $Root)
$server.Start()
