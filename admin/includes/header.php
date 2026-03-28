<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Admin System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        /* 1. BACKGROUND GRADIENT (Wajib biar Glassmorphism kelihatan) */
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            padding-bottom: 80px; /* Jarak aman untuk Bottom Bar di HP */
        }

        /* 2. STYLE GLASSMORPHISM (Efek Kaca) */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card {
            background: rgba(255, 255, 255, 0.15) !important; /* Transparan */
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 15px;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
        }
        .form-control::placeholder { color: rgba(255,255,255, 0.7); }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: none;
        }
        
        /* Table Style */
        .table { color: white; }
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            color: white;
            background-color: rgba(255, 255, 255, 0.05);
        }

        /* 3. SIDEBAR / NAVBAR LOGIC */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 250px;
            padding: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            margin-left: 260px; /* Jarak supaya gak ketutup sidebar */
            padding: 20px;
        }

        /* Link Menu */
        .nav-link {
            color: rgba(255,255,255,0.8);
            margin-bottom: 10px;
            border-radius: 10px;
            padding: 12px 15px;
            transition: 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255, 0.25);
            color: white;
            font-weight: bold;
        }

        /* 4. RESPONSIVE (Tampilan HP) */
        @media (max-width: 768px) {
            .sidebar {
                top: auto; bottom: 0; left: 0; right: 0;
                width: 100%;
                height: 70px;
                flex-direction: row;
                justify-content: space-around;
                padding: 10px;
                border-top: 1px solid rgba(255,255,255,0.2);
                border-right: none;
            }
            
            .sidebar h4 { display: none; } /* Sembunyikan Logo di HP */
            
            .nav-link {
                text-align: center;
                font-size: 0.8rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 5px;
                margin: 0;
            }
            
            .nav-link i { font-size: 1.5rem; margin-bottom: 2px; }
            .nav-link span { display: none; } /* Sembunyikan Teks di HP biar muat, atau kecilkan */
            
            .main-content {
                margin-left: 0;
                margin-bottom: 70px;
            }
        }
    </style>
</head>
<body>