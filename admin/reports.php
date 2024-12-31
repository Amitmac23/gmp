<?php
require_once '../config/config.php';

date_default_timezone_set('Asia/Kolkata');

function fetchStats($pdo, $type, $startDate = null, $endDate = null) {
    $dateFilter = '';
    switch ($type) {
        case 'daily':
            $dateFilter = "DATE(start_time) = CURRENT_DATE";
            break;
        case 'yesterday':
            $dateFilter = "DATE(start_time) = CURDATE() - INTERVAL 1 DAY";
            break;
        case 'monthly':
            $dateFilter = "MONTH(start_time) = MONTH(CURRENT_DATE) AND YEAR(start_time) = YEAR(CURRENT_DATE)";
            break;
        case 'yearly':
            $dateFilter = "YEAR(start_time) = YEAR(CURRENT_DATE)";
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $dateFilter = "DATE(start_time) BETWEEN :start_date AND :end_date";
            }
            break;
    }

    $query = "
        SELECT 
            g.name AS game_name, 
            COUNT(b.id) AS total_bookings, 
            SUM(b.customer_id) AS total_players, 
            SUM(b.total_price) AS total_revenue
        FROM 
            bookings b
        JOIN 
            games g ON b.game_id = g.id
        WHERE 
            $dateFilter
        GROUP BY 
            b.game_id
        ORDER BY 
            total_bookings DESC
    ";

    $stmt = $pdo->prepare($query);

    if ($type === 'custom' && $startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_POST['action']) && $_POST['action'] === 'filter') {
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $customStats = fetchStats($pdo, 'custom', $startDate, $endDate);
    echo json_encode($customStats);
    exit;
}

// Fetch other default statistics (daily, yesterday, monthly, yearly)
$dailyStats = fetchStats($pdo, 'daily');
$yesterdayStats = fetchStats($pdo, 'yesterday');
$monthlyStats = fetchStats($pdo, 'monthly');
$yearlyStats = fetchStats($pdo, 'yearly');
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'sidebar.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <!-- Date Filter Section -->
        <div class="col-md-12 mb-4">
            <div class="d-flex justify-content-start">
                <input type="date" id="startDate" class="form-control me-2" style="width: 200px;">
                <input type="date" id="endDate" class="form-control" style="width: 200px;">
                <button id="filterBtn" class="btn btn-primary ms-2">Filter</button>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="col-md-6 offset-md-6 mb-4">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">Reports</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-center mb-3">
                        <select id="chartTypeSelector" class="form-select w-75" style="font-size: 14px;">
                            <option value="daily">Daily</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div style="width: 100%; height: 300px;">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div id="tableContainer">
        <h5 class="text-primary" id="tableTitle">Daily Report</h5>
        <table class="table table-striped table-hover">
            <thead class="table-primary">
                <tr>
                    <th>Game</th>
                    <th>Total Bookings</th>
                    <th>Total Players</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody id="statsTableBody">
                <?php foreach ($dailyStats as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['game_name']); ?></td>
                        <td><?php echo $stat['total_bookings']; ?></td>
                        <td><?php echo $stat['total_players']; ?></td>
                        <td>₹<?php echo number_format($stat['total_revenue'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const dailyData = <?php echo json_encode($dailyStats); ?>;
    const yesterdayData = <?php echo json_encode($yesterdayStats); ?>;
    const monthlyData = <?php echo json_encode($monthlyStats); ?>;
    const yearlyData = <?php echo json_encode($yearlyStats); ?>;

    function extractChartData(data) {
        return {
            labels: data.map(item => item.game_name),
            datasets: [{
                label: 'Bookings',
                data: data.map(item => item.total_bookings),
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                hoverOffset: 4
            }]
        };
    }

    const ctx = document.getElementById('statsChart').getContext('2d');
    let statsChart = new Chart(ctx, {
        type: 'doughnut',
        data: extractChartData(dailyData),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
            }
        }
    });

    document.getElementById('chartTypeSelector').addEventListener('change', function (e) {
        let selectedData;
        let selectedTitle;

        if (e.target.value === 'daily') {
            selectedData = dailyData;
            selectedTitle = 'Daily Report';
        } else if (e.target.value === 'yesterday') {
            selectedData = yesterdayData;
            selectedTitle = 'Yesterday\'s Report';
        } else if (e.target.value === 'monthly') {
            selectedData = monthlyData;
            selectedTitle = 'Monthly Report';
        } else if (e.target.value === 'yearly') {
            selectedData = yearlyData;
            selectedTitle = 'Yearly Report';
        }

        statsChart.data = extractChartData(selectedData);
        statsChart.update();

        document.getElementById('tableTitle').textContent = selectedTitle;
        const tableBody = document.getElementById('statsTableBody');
        tableBody.innerHTML = '';
        selectedData.forEach(stat => {
            tableBody.innerHTML += `
                <tr>
                    <td>${stat.game_name}</td>
                    <td>${stat.total_bookings}</td>
                    <td>${stat.total_players}</td>
                    <td>₹${Number(stat.total_revenue).toFixed(2)}</td>
                </tr>
            `;
        });
    });

    document.getElementById('filterBtn').addEventListener('click', function () {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (startDate && endDate) {
            const formData = new FormData();
            formData.append('action', 'filter');
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);

            // Make an AJAX request to filter data
            fetch('reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                statsChart.data = extractChartData(data);
                statsChart.update();
                document.getElementById('tableTitle').textContent = 'Custom Report';
                const tableBody = document.getElementById('statsTableBody');
                tableBody.innerHTML = '';
                data.forEach(stat => {
                    tableBody.innerHTML += `
                        <tr>
                            <td>${stat.game_name}</td>
                            <td>${stat.total_bookings}</td>
                            <td>${stat.total_players}</td>
                            <td>₹${Number(stat.total_revenue).toFixed(2)}</td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    });
</script>

</body>
</html>
