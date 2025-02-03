<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* General Styles */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Navbar Styles */
        .navbar {
            background-color: #343a40;
        }

        .navbar-brand,
        .navbar-nav .nav-link {
            color: white !important;
        }

        .navbar-brand:hover,
        .navbar-nav .nav-link:hover {
            color: #007bff !important;
        }

        /* Toggle Button */
        .navbar-toggler {
            border: none;
            background: none;
            padding: 0;
        }

        .navbar-toggler-icon {
            position: relative;
            display: inline-block;
            width: 30px;
            height: 3px;
            background-color: white;
            border-radius: 2px;
            transition: all 0.3s ease-in-out;
        }

        .navbar-toggler-icon::before,
        .navbar-toggler-icon::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 3px;
            background-color: white;
            border-radius: 2px;
            transition: all 0.3s ease-in-out;
        }

        .navbar-toggler-icon::before {
            top: -8px;
        }

        .navbar-toggler-icon::after {
            top: 8px;
        }

        /* Cross Animation */
        .navbar-toggler.collapsed .navbar-toggler-icon {
            background-color: transparent;
        }

        .navbar-toggler.collapsed .navbar-toggler-icon::before {
            transform: rotate(45deg);
            top: 0;
        }

        .navbar-toggler.collapsed .navbar-toggler-icon::after {
            transform: rotate(-45deg);
            top: 0;
        }

        /* Main Content */
        .main-content {
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <button class="navbar-toggler" type="button" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/manage_games.php">Manage Games</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/manage_tables.php">Manage Tables</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/player_history.php">Player History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/manage_table_games.php">Assign Table</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://localhost/gmp/admin/logout.php">Log Out</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Custom Toggle Button Logic
        document.querySelector('.navbar-toggler').addEventListener('click', function () {
            const navbarCollapse = document.getElementById('navbarNav');
            this.classList.toggle('collapsed');
            navbarCollapse.classList.toggle('show');
        });
    </script>
</body>
</html>
