<?php
// index.php

// Include the config file
require 'config.php';

// Get the user's IP address
$user_ip = $_SERVER['REMOTE_ADDR'];

// Validate target input
function isValidTarget($target) {
    return filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ||
           preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $target);
}

// Define allowed commands
$allowed_commands = [
    'ping' => ['command' => 'ping', 'args' => '-c 4'],
    'ping6' => ['command' => 'ping6', 'args' => '-c 4'],
    'traceroute' => ['command' => 'traceroute', 'args' => ''],
    'traceroute6' => ['command' => 'traceroute', 'args' => ''],
    'mtr' => ['command' => 'mtr', 'args' => '--report'],
];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $command = $_POST['command'];
    $target = $_POST['target'];

    // Validate target
    if (!isValidTarget($target)) {
        echo "Invalid target. Please enter a valid IP address or hostname.";
    } elseif (!array_key_exists($command, $allowed_commands)) {
        echo "Invalid command.";
    } else {
        // Construct the command securely
        $cmd = $allowed_commands[$command]['command'] . ' ' .
               $allowed_commands[$command]['args'] . ' ' .
               escapeshellarg($target);

        // Execute the command
        $output = shell_exec($cmd);
        if ($output === null) {
            echo "Command failed to execute.";
        } else {
            echo htmlspecialchars($output);
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Looking Glass Server</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="leaflet.css" />
</head>
<body>
    <div class="container">
		<div class="info">
			<!-- Display server information -->
			<div class="server-info">
				<h1><?php echo $server_info['title']; ?></h1>
				<h2>Server Information</h2>
				<p><strong>Location:</strong> <?php echo $server_info['location']; ?></p>
				<p><strong>Datacenter:</strong> <?php echo $server_info['dc']; ?></p>
				<p><strong>IPv4:</strong> <?php echo $server_info['ipv4']; ?></p>
				<p><strong>IPv6:</strong> <?php echo $server_info['ipv6']; ?></p>
				<p><strong>AS Number:</strong> <?php echo $server_info['asn']; ?></p>
				<!-- Display user's IP address -->
				<div class="user-info">
					<h2>Your IP Address</h2>
					<p><?php echo $user_ip; ?></p>
				</div>
			</div>
			
			<div id="map"></div>
		</div>
		<script src="leaflet.js"></script>
		<!-- Test download files -->
		<div class="download-links">
			<h2>Test Download Files</h2>
			<div class="download">
				<?php foreach ($test_files as $size => $file): ?>
					<div class="file">
						<a href="<?php echo $file; ?>" download><?php echo $size; ?></a>
						<div>
						<input onclick="copyText('url-<?php echo $file; ?>')" type="text" id="url-<?php echo $file; ?>" value="https:/<?php echo $_SERVER['HTTP_HOST'];?>/<?php echo $file; ?>" readonly>
						<img onclick="copyText('url-<?php echo $file; ?>')" src="images/copy.svg" alt="Icon" width="10" height="10">
						</div>
						<div>
						<input onclick="copyText('curl-<?php echo $file; ?>')" type="text" id="curl-<?php echo $file; ?>" value="curl -o /dev/null https:/<?php echo $_SERVER['HTTP_HOST'];?>/<?php echo $file; ?>" readonly>
						<img onclick="copyText('curl-<?php echo $file; ?>')" src="images/copy.svg" alt="Icon" width="10" height="10">
						</div>
						<div>
						<input onclick="copyText('wget-<?php echo $file; ?>')" type="text" id="wget-<?php echo $file; ?>" value="wget -O /dev/null https:/<?php echo $_SERVER['HTTP_HOST'];?>/<?php echo $file; ?>" readonly>
						<img onclick="copyText('wget-<?php echo $file; ?>')" src="images/copy.svg" alt="Icon" width="10" height="10">
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		

        <!-- Looking Glass form -->
		<div class="lg">
			<h2>Looking Glass</h2>
			<form id="lg-form">
				<label for="target">Target IP/Hostname:</label>
				<input type="text" id="target" name="target" required>
				<br><br>
				<label for="command">Command:</label>
				<select id="command" name="command" required>
					<option value="ping">ping</option>
					<option value="ping6">ping6</option>
					<option value="traceroute">traceroute</option>
					<option value="traceroute6">traceroute6</option>
					<option value="mtr">mtr</option>
					<option value="mtr6">mtr6</option>
				</select>
				<br><br>
				<button type="submit">Run</button>
			</form>

			<h2>Output:</h2>
			<textarea id="output" readonly></textarea>
		</div>
    </div>
	<script>
		document.getElementById('lg-form').addEventListener('submit', function(event) {
			event.preventDefault(); // Prevent form submission

			var formData = new FormData(this);
			formData.append('ajax', true); // Append AJAX flag

			fetch('', {
				method: 'POST',
				body: formData
			})
			.then(response => response.text())
			.then(data => {
				// Update textarea with the output
				document.getElementById('output').value = data;
			})
			.catch(error => {
				document.getElementById('output').value = 'Error: ' + error;
			});
		});
		
        function copyText(id) {
            var textBox = document.getElementById(id);
            textBox.select();
            textBox.setSelectionRange(0, 99999);
            document.execCommand("copy");
        }
		
		// Set the latitude and longitude for the pin
		const lat = <?php echo $server_info['lat']; ?>;
		const lon = <?php echo $server_info['lon']; ?>;

		// Initialize the map
		const map = L.map('map').setView([lat, lon], 13);

		// Set up the OpenStreetMap tile layer
		L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
		  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CartoDB</a>'
		}).addTo(map);

		// Add a marker at the specified latitude and longitude
		L.marker([lat, lon]).addTo(map)
		  .bindPopup('<?php echo $server_info['dc']; ?>')
		  .openPopup();
	</script>
</body>
</html>