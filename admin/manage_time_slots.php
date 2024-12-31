<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $starting_time = $_POST['start_time'];
    $ending_time = $_POST['end_time'];
    $game_id = 1; // Set the correct game_id here

    $stmt = $pdo->query("SELECT * FROM time_slots LIMIT 1");
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE time_slots SET starting_time = ?, ending_time = ?, game_id = ? WHERE id = ?");
        $stmt->execute([$starting_time, $ending_time, $game_id, $existing['id']]);
        $message = "Time range updated successfully!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO time_slots (starting_time, ending_time, game_id) VALUES (?, ?, ?)");
        $stmt->execute([$starting_time, $ending_time, $game_id]);
        $message = "Time range set successfully!";
    }
}

$stmt = $pdo->query("SELECT * FROM time_slots LIMIT 1");
$time_range = $stmt->fetch();
?>

<!-- Add these in the head section of your HTML -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
    body {
        background-color: #f8f9fa;
    }
    .card {
        margin-top: 20px;
    }
    .form-control {
        border-radius: 10px;
    }
    .btn {
        border-radius: 25px;
    }
    @media (max-width: 768px) {
        .card {
            margin: 10px;
        }
    }
</style>

<div class="container mt-5">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0">Manage Time Slots</h4>
        </div>
        <div class="card-body">
            <?php if (isset($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="start_time" class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" id="start_time"
                               value="<?php echo $time_range['starting_time'] ?? ''; ?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="end_time" class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" id="end_time"
                               value="<?php echo $time_range['ending_time'] ?? ''; ?>" required>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="fas fa-save me-2"></i> Save Time Range
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
