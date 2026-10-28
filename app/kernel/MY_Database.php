<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * HOTELGO - MY_Database
 *
 * Extends the framework's Database class the way LavaLust allows it (subclass_prefix 'MY_' in
 * app/config/config.php, file in app/kernel). Nothing inside scheme/ is changed.
 *
 * Why: Aiven only accepts an encrypted MySQL connection, so the CA certificate has to be given to
 * PDO. On PHP 8.4+ the old constant PDO::MYSQL_ATTR_SSL_CA is deprecated (PHP 8.5 reports it), and the
 * framework's error handler turns that notice into a fatal error. This class opens the connection with
 * the new constant Pdo\Mysql::ATTR_SSL_CA and hands it to the framework's Database class.
 *
 * It only acts when DB_SSL_CA is set and the driver is mysql. In every other case the framework's own
 * constructor runs unchanged.
 */
class MY_Database extends Database
{
    /**
     * Same as Database::instance() but creates this class.
     *
     * @param string|null $dbname
     * @return static
     */
    public static function instance($dbname)
    {
        return new static($dbname);
    }

    /**
     * @param string|null $dbname
     */
    public function __construct($dbname = NULL)
    {
        $ca = getenv('DB_SSL_CA');
        $config = is_null($dbname) ? (database_config()['main'] ?? []) : (database_config()[$dbname] ?? []);
        $driver = strtolower($config['driver'] ?? 'mysql');

        if ($driver !== 'mysql' || ! $ca) {
            parent::__construct($dbname);
            return;
        }

        $host    = ! empty($config['hostname']) ? $config['hostname'] : 'localhost';
        $port    = ! empty($config['port']) ? $config['port'] : null;
        $name    = $config['database'] ?? '';
        $charset = ! empty($config['charset']) ? $config['charset'] : 'utf8mb4';
        $user    = ! empty($config['username']) ? $config['username'] : 'root';
        $pass    = $config['password'] ?? '';
        $prefix  = $config['dbprefix'] ?? '';

        $sslCa = class_exists('Pdo\Mysql') ? constant('Pdo\Mysql::ATTR_SSL_CA') : PDO::MYSQL_ATTR_SSL_CA;
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            $sslCa                       => $ca,
        );

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=$charset;port=$port", $user, $pass, $options);
        } catch (Exception $e) {
            $error = load_class('Errors', 'kernel');
            $error->show_database_error($e->getMessage(), '', [], $e);
            return;
        }

        // give the finished connection to the framework class (its properties are private)
        $adopt = function ($pdo, $prefix) {
            $this->db        = $pdo;
            $this->driver    = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->db_prefix = $prefix;
        };
        Closure::bind($adopt, $this, Database::class)($pdo, $prefix);
    }
}
