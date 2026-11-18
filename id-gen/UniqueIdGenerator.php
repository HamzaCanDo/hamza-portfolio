<?php

class UniqueIdGenerator {
    private $pdo;

    public function __construct() {
        $config = require __DIR__ . '/db_config.php';

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['db']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function generateId($length = 8) {
        do {
            $id = $this->generateRandomId($length);

            try {
                $this->storeId($id);
                return $id;
            } catch (PDOException $exception) {
                // The database unique index is the final protection against
                // duplicates when multiple requests run at the same time.
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
            }
        } while (true);
    }

    private function generateRandomId($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $id;
    }

    private function storeId($id) {
        $stmt = $this->pdo->prepare("INSERT INTO generated_ids (unique_id) VALUES (:id)");
        $stmt->execute(['id' => $id]);
    }

    public function getAllGeneratedIds() {
        $stmt = $this->pdo->query("SELECT unique_id FROM generated_ids");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}


// $idGenerator = new UniqueIdGenerator();
// echo $idGenerator->generateId();
?>
