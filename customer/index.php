<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Booking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <style>
        body {
            margin: 0;
            font-family: 'Press Start 2P', cursive;
            color: white;
            background: url('../assets/images/gaming-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        .hero {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); /* Semi-transparent overlay */
            z-index: 0;
        }
        .hero h1 {
            font-size: 3rem;
            text-shadow: 2px 2px 10px black;
            z-index: 1; /* Ensures text stays above overlay */
            position: relative;
        }
        .hero button {
            background: linear-gradient(90deg, #ff8a00, #e52e71);
            border: none;
            padding: 15px 30px;
            font-size: 1.5rem;
            border-radius: 10px;
            box-shadow: 0px 0px 20px #e52e71;
            color: white;
            animation: glowing 1.5s infinite;
            z-index: 1; /* Ensures button stays above overlay */
            position: relative;
        }
        @keyframes glowing {
            0% { box-shadow: 0 0 5px #e52e71; }
            50% { box-shadow: 0 0 20px #ff8a00; }
            100% { box-shadow: 0 0 5px #e52e71; }
        }
    </style>
</head>
<body>
    <div class="hero" data-aos="zoom-in">
        <h1>Welcome to Game Booking</h1>
        <button onclick="startBooking()">Start</button>
    </div>

    <script>
        function startBooking() {
            window.location.href = "register.php";
        }
    </script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init();</script>
</body>
</html>
