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
            SUM(b.player_count) AS total_players, 
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

function fetchTotalStats($pdo, $type, $startDate = null, $endDate = null) {
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
            SUM(b.total_price) AS total_revenue
        FROM 
            bookings b
        WHERE 
            $dateFilter
    ";

    $stmt = $pdo->prepare($query);

    if ($type === 'custom' && $startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch revenue statistics
$dailyRevenue = fetchTotalStats($pdo, 'daily');
$yesterdayRevenue = fetchTotalStats($pdo, 'yesterday');
$monthlyRevenue = fetchTotalStats($pdo, 'monthly');
$yearlyRevenue = fetchTotalStats($pdo, 'yearly');

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
    <!-- Info Boxes Section -->
    <div class="row mb-4">
        <!-- Daily Revenue -->
        <div class="col-md-3 mb-3">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center">
                    <h6 class="text-muted">Daily Revenue</h6>
                    <h5 class="text-primary">₹<?php echo number_format($dailyRevenue['total_revenue'] ?? 0, 2); ?></h5>
                </div>
            </div>
        </div>

        <!-- Yesterday Revenue -->
        <div class="col-md-3 mb-3">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center">
                    <h6 class="text-muted">Yesterday Revenue</h6>
                    <h5 class="text-success">₹<?php echo number_format($yesterdayRevenue['total_revenue'] ?? 0, 2); ?></h5>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="col-md-3 mb-3">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center">
                    <h6 class="text-muted">Monthly Revenue</h6>
                    <h5 class="text-warning">₹<?php echo number_format($monthlyRevenue['total_revenue'] ?? 0, 2); ?></h5>
                </div>
            </div>
        </div>

        <!-- Yearly Revenue -->
        <div class="col-md-3 mb-3">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center">
                    <h6 class="text-muted">Yearly Revenue</h6>
                    <h5 class="text-danger">₹<?php echo number_format($yearlyRevenue['total_revenue'] ?? 0, 2); ?></h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
    
       <div class="col-md-12 mb-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center">
            <h5 class="mb-0">Reports</h5>
        </div>
        <div class="card-body">
 
           <!-- Chart Type Selector and Date Filter Section -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <!-- Date Filter Section -->
    <div class="d-flex align-items-center">
        <input type="date" id="startDate" class="form-control me-2" style="width: 150px;">
        <input type="date" id="endDate" class="form-control me-2" style="width: 150px;">
        <button id="filterBtn" class="btn btn-primary">Filter</button>
    </div>

    <!-- Chart Type Selector -->
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <label for="chartTypeSelector" class="me-2 text-secondary">Chart Type:</label>
        <select id="chartTypeSelector" class="form-select" style="width: 200px;">
            <option value="daily">Daily</option>
            <option value="yesterday">Yesterday</option>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
        </select>
    </div>
</div>


            <!-- Charts Section -->
            <div class="row">
                <div class="col-md-6">
                    <div style="width: 100%; height: 300px;">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="width: 100%; height: 300px;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table Section -->
<div id="tableContainer" class="mt-4">
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
// Example Data from PHP
const dailyData = <?php echo json_encode($dailyStats); ?>;
const yesterdayData = <?php echo json_encode($yesterdayStats); ?>;
const monthlyData = <?php echo json_encode($monthlyStats); ?>;
const yearlyData = <?php echo json_encode($yearlyStats); ?>;

// Extract Chart Data
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

// Initialize Charts
const ctx = document.getElementById('statsChart').getContext('2d');
const barCtx = document.getElementById('barChart').getContext('2d');

let statsChart = new Chart(ctx, {
    type: 'doughnut',
    data: extractChartData(dailyData),
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});

let barChart = new Chart(barCtx, {
    type: 'bar',
    data: extractChartData(dailyData),
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Games'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Bookings'
                },
                beginAtZero: true
            }
        }
    }
});

// Update Chart and Table on Selector Change
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

    barChart.data = extractChartData(selectedData);
    barChart.update();

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

// Filter Data by Date
document.getElementById('filterBtn').addEventListener('click', function () {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (startDate && endDate) {
        const formData = new FormData();
        formData.append('action', 'filter');
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        fetch('reports.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                statsChart.data = extractChartData(data);
                statsChart.update();

                barChart.data = extractChartData(data);
                barChart.update();

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
