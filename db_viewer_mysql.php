<?php
/**
 * AquaSense MySQL Database Visualizer
 * Open this file in browser to see MySQL database structure and data
 * URL: http://aquasense.local/db_viewer_mysql.php
 */

// MySQL connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'aquasense';

try {
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        die("<h2 style='color:red'>MySQL connection failed</h2><p>" . $mysqli->connect_error . "</p>");
    }
} catch (Exception $e) {
    die("<h2 style='color:red'>MySQL connection failed</h2><p>" . $e->getMessage() . "</p>");
}

// Get all tables
$tables = [];
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AquaSense MySQL Database Viewer</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; padding: 20px; }
        h1 { color: #2E7D32; margin-bottom: 20px; text-align: center; }
        h2 { color: #4CAF50; margin: 30px 0 15px 0; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-box { background: #E8F5E9; padding: 15px 25px; border-radius: 5px; text-align: center; }
        .stat-box h3 { color: #2E7D32; font-size: 24px; }
        .stat-box p { color: #666; font-size: 12px; }
        table { width: 100%; background: white; border-collapse: collapse; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th { background: #4CAF50; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; }
        tr:hover { background: #f5f5f5; }
        .schema { background: #263238; color: #aed581; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; margin-bottom: 20px; }
        .nav { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .nav a { background: #4CAF50; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .nav a:hover { background: #2E7D32; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-pk { background: #FF5722; color: white; }
        .badge-fk { background: #2196F3; color: white; }
        .badge-auto { background: #9C27B0; color: white; }
        .erd { background: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .erd svg { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌱 AquaSense MySQL Database Viewer</h1>
        
        <!-- Database Stats -->
        <div class="stats">
            <?php foreach ($tables as $table): 
                $count = $mysqli->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0];
            ?>
            <div class="stat-box">
                <h3><?= number_format($count) ?></h3>
                <p><?= ucfirst(str_replace('_', ' ', $table)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Navigation -->
        <div class="nav">
            <a href="#users">Users</a>
            <a href="#farms">Farms</a>
            <a href="#sensor_readings">Sensor Readings</a>
            <a href="#irrigation_events">Irrigation</a>
            <a href="#system_alerts">Alerts</a>
            <a href="#app_state">App State</a>
        </div>

        <!-- ERD Section -->
        <h2 id="erd">📊 Entity Relationship Diagram</h2>
        <div class="erd">
            <svg viewBox="0 0 800 500" xmlns="http://www.w3.org/2000/svg">
                <!-- Users Table -->
                <rect x="50" y="50" width="150" height="120" fill="#E8F5E9" stroke="#4CAF50" stroke-width="2"/>
                <text x="125" y="75" text-anchor="middle" font-weight="bold" fill="#2E7D32">USERS</text>
                <line x1="60" y1="85" x2="190" y2="85" stroke="#4CAF50"/>
                <text x="60" y="105" font-size="11" fill="#333">user_id (PK)</text>
                <text x="60" y="120" font-size="11" fill="#333">name</text>
                <text x="60" y="135" font-size="11" fill="#333">email</text>
                <text x="60" y="150" font-size="11" fill="#333">phone</text>

                <!-- Farms Table -->
                <rect x="300" y="50" width="150" height="140" fill="#E3F2FD" stroke="#2196F3" stroke-width="2"/>
                <text x="375" y="75" text-anchor="middle" font-weight="bold" fill="#1976D2">FARMS</text>
                <line x1="310" y1="85" x2="440" y2="85" stroke="#2196F3"/>
                <text x="310" y="105" font-size="11" fill="#333">farm_id (PK)</text>
                <text x="310" y="120" font-size="11" fill="#333">user_id (FK)</text>
                <text x="310" y="135" font-size="11" fill="#333">location</text>
                <text x="310" y="150" font-size="11" fill="#333">crop_type</text>
                <text x="310" y="165" font-size="11" fill="#333">size</text>

                <!-- Sensor Data Table -->
                <rect x="550" y="200" width="180" height="120" fill="#F3E5F5" stroke="#9C27B0" stroke-width="2"/>
                <text x="640" y="225" text-anchor="middle" font-weight="bold" fill="#7B1FA2">SENSOR_READINGS</text>
                <line x1="560" y1="235" x2="720" y2="235" stroke="#9C27B0"/>
                <text x="560" y="255" font-size="11" fill="#333">reading_id (PK)</text>
                <text x="560" y="270" font-size="11" fill="#333">farm_id (FK)</text>
                <text x="560" y="285" font-size="11" fill="#333">soil_moisture</text>
                <text x="560" y="300" font-size="11" fill="#333">temperature</text>
                <text x="560" y="315" font-size="11" fill="#333">timestamp</text>

                <!-- Irrigation Events -->
                <rect x="300" y="250" width="170" height="120" fill="#FFEBEE" stroke="#F44336" stroke-width="2"/>
                <text x="385" y="275" text-anchor="middle" font-weight="bold" fill="#C62828">IRRIGATION_EVENTS</text>
                <line x1="310" y1="285" x2="460" y2="285" stroke="#F44336"/>
                <text x="310" y="305" font-size="11" fill="#333">event_id (PK)</text>
                <text x="310" y="320" font-size="11" fill="#333">farm_id (FK)</text>
                <text x="310" y="335" font-size="11" fill="#333">trigger_type</text>
                <text x="310" y="350" font-size="11" fill="#333">start_time</text>
                <text x="310" y="365" font-size="11" fill="#333">water_used</text>

                <!-- System Alerts -->
                <rect x="50" y="250" width="150" height="120" fill="#ECEFF1" stroke="#607D8B" stroke-width="2"/>
                <text x="125" y="275" text-anchor="middle" font-weight="bold" fill="#455A64">SYSTEM_ALERTS</text>
                <line x1="60" y1="285" x2="190" y2="285" stroke="#607D8B"/>
                <text x="60" y="305" font-size="11" fill="#333">alert_id (PK)</text>
                <text x="60" y="320" font-size="11" fill="#333">farm_id (FK)</text>
                <text x="60" y="335" font-size="11" fill="#333">alert_type</text>
                <text x="60" y="350" font-size="11" fill="#333">message</text>
                <text x="60" y="365" font-size="11" fill="#333">resolved</text>

                <!-- App State -->
                <rect x="550" y="400" width="150" height="80" fill="#FFF9C4" stroke="#FBC02D" stroke-width="2"/>
                <text x="625" y="425" text-anchor="middle" font-weight="bold" fill="#F57F17">APP_STATE</text>
                <line x1="560" y1="435" x2="690" y2="435" stroke="#FBC02D"/>
                <text x="560" y="455" font-size="11" fill="#333">key (PK)</text>
                <text x="560" y="470" font-size="11" fill="#333">value</text>

                <!-- Relationship Lines -->
                <line x1="200" y1="110" x2="300" y2="110" stroke="#666" stroke-width="2" marker-end="url(#arrow)"/>
                <line x1="375" y1="190" x2="385" y2="250" stroke="#666" stroke-width="2" marker-end="url(#arrow)"/>
                <line x1="470" y1="300" x2="550" y2="280" stroke="#666" stroke-width="2" marker-end="url(#arrow)"/>
                <line x1="300" y1="300" x2="200" y2="310" stroke="#666" stroke-width="2" marker-end="url(#arrow)"/>

                <!-- Arrow marker -->
                <defs>
                    <marker id="arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                        <path d="M0,0 L0,6 L9,3 z" fill="#666"/>
                    </marker>
                </defs>

                <!-- Legend -->
                <text x="50" y="480" font-size="12" fill="#666">PK = Primary Key | FK = Foreign Key</text>
                <text x="50" y="500" font-size="12" fill="#666">Lines show relationships between tables</text>
            </svg>
        </div>

        <!-- Table Details -->
        <?php foreach ($tables as $table): ?>
        <h2 id="<?= $table ?>">📋 <?= ucfirst(str_replace('_', ' ', $table)) ?></h2>
        
        <!-- Schema -->
        <div class="schema"><?php
            $result = $mysqli->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_assoc();
            echo htmlspecialchars($row['Create Table']);
        ?></div>

        <!-- Sample Data -->
        <?php
        $columns = [];
        $result = $mysqli->query("SHOW COLUMNS FROM `$table`");
        while ($col = $result->fetch_assoc()) {
            $columns[] = $col;
        }
        
        $data = $mysqli->query("SELECT * FROM `$table` LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $col): 
                        $badge = '';
                        if ($col['Key'] === 'PRI') $badge = '<span class="badge badge-pk">PK</span>';
                        elseif ($col['Key'] === 'MUL') $badge = '<span class="badge badge-fk">FK</span>';
                        elseif ($col['Extra'] === 'auto_increment') $badge = '<span class="badge badge-auto">AI</span>';
                    ?>
                    <th><?= $col['Field'] ?> <?= $badge ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr><td colspan="<?= count($columns) ?>" style="text-align:center;color:#999">No data in this table</td></tr>
                <?php else: ?>
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($row as $key => $value): 
                        $display = $value;
                        if (strlen($value) > 50) $display = substr($value, 0, 50) . '...';
                        if ($value === null) $display = '<span style="color:#999">NULL</span>';
                    ?>
                    <td><?= $display ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

        <div style="text-align:center; margin-top:40px; color:#666; font-size:12px;">
            <p>AquaSense MySQL Database Viewer | <?= date('Y-m-d H:i:s') ?></p>
            <p>Database: <?= $dbname ?> @ <?= $host ?></p>
        </div>
    </div>
</body>
</html>
