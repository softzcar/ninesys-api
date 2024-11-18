<?php
class LocalDB
{
    protected $sql;
    private $pdo;

    public function __construct($sql = '')
    {
        $this->sql = $sql;
    }

    private function connectToDatabase()
    {
        try {
            $this->pdo = new PDO(
                LOCAL_DSN,
                LOCAL_USER,
                LOCAL_PASS,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
                )
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /* private function connectToCopy()
    {
        try {
            $this->pdo = new PDO(
                LOCAL_DSN_COPY,
                LOCAL_USER,
                LOCAL_PASS,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET lc_time_names = 'es_ES', NAMES utf8"
                )
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    } */

    public function disconnect()
    {
        unset($this->pdo); // Desconectar cerrando la conexión PDO
    }

    public function setSql($sql)
    {
        $this->sql = $sql;
    }

    public function insert()
    {
        $mat = array();
        try {
            // Crear nueva orden
            $res = $this->pdo->prepare($this->sql);
            $res->execute();
            $mat = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }

        return $mat;
    }

    public function goQueryOLD()
    {
        $this->connectToDatabase();
        $mat = array();
        try {
            $res = $this->pdo->prepare($this->sql);
            $res->execute();

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }

        return $mat;
    }

    public function goQuery($sql = '')
    {
        $this->connectToDatabase();
        $mat = array();
        try {
            $res = $this->pdo->prepare($sql);
            $res->execute();

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;
            // $lastInsertId = $this->pdo->lastInsertId(); // Obtener el ID
            // $mat['last_insert_id'] = $lastInsertId; // Agregar el ID a los datos
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo();
            $mat["sql"] = $this->sql;
            $mat['status'] = 'error';
            $mat['message'] = "Error al ejecutar la consulta: " . $e->getMessage() . ". Detalles: " . $errorInfo[2];
        }

        return $mat;
    }
    /* public function goQueryCopy($sql)
    {
        $this->connectToCopy();
        $mat = array();
        try {
            $res = $this->pdo->prepare($sql);
            $res->execute();

            $data = $res->fetchAll(PDO::FETCH_ASSOC);
            $mat = $data;
        } catch (PDOException $e) {
            $mat['status'] = 'error';
            $mat['message'] = $e->getMessage();
        }

        return $mat;
    } */

    public function getLastID()
    {
        return $this->pdo->lastInsertId();
    }
}
