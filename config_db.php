<?php

     $database_url = getenv('postgresql://neondb_owner:npg_uQLmSvk2tJO6@ep-old-snow-aps7gnlr-pooler.c-7.us-east-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require');
    
     if (!$database_url) {
         die("Errore: La configurazione DATABASE_URL non è impostata.");
     }
   
    try {
        // Parsing della stringa di connessione per PDO
        $url_parts = parse_url($database_url);
   
     $host = $url_parts['host'];
     $port = $url_parts['port'] ?? 5432;
    $dbname = ltrim($url_parts['path'], '/');
     $user = $url_parts['user'];
    $password = $url_parts['pass'];
   
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
   

     $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
   
    } catch (PDOException $e) {
        // Se fallisce, ti darà un errore. Se in produzione, meglio nascondere il dettaglio.
        die("Errore di connessione al database.");
    }
		 ?>
