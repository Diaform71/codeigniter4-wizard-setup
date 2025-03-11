<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Codeigniter\Database\Config;

class InstallController extends BaseController
{
    protected $session;
    protected $validation;
    protected $installer_version = '1.0.0';
    protected $db_connection = false;

    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->validation = \Config\Services::validation();
        helper(['form', 'url', 'filesystem']);
    }
    public function index()
    {
        // Verifica se è già installato
        if (file_exists(WRITEPATH . 'installed.txt')) {
            return redirect()->to(base_url());
        }

        return view('install/welcome', [
            'title' => 'Installazione Applicazione',
            'version' => $this->installer_version
        ]);
    }

    public function requirements()
    {
        if (file_exists(WRITEPATH . 'installed.txt')) {
            return redirect()->to(base_url());
        }

        // Verifica requisiti PHP
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'curl' => extension_loaded('curl'),
            'intl' => extension_loaded('intl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'xml' => extension_loaded('xml'),
            'writable_env' => is_really_writable(ROOTPATH . '.env'),
            'writable_writepath' => is_really_writable(WRITEPATH),
            'writable_uploadpath' => is_really_writable(ROOTPATH . 'public/uploads'),
        ];

        // Verifica se tutti i requisiti sono soddisfatti
        $requirements_satisfied = !in_array(false, $requirements);

        return view('install/requirements', [
            'title' => 'Verifica Requisiti',
            'requirements' => $requirements,
            'requirements_satisfied' => $requirements_satisfied
        ]);
    }

    public function database()
    {
        $request = \Config\Services::request();
        if (file_exists(WRITEPATH . 'installed.txt')) {
            return redirect()->to(base_url());
        }

        if ($request->getMethod() == 'POST') {
            $rules = [
                'hostname' => 'required',
                'username' => 'required',
                'database' => 'required',
                'port' => 'required|numeric'
            ];

            if ($this->validate($rules)) {
                // Salva i parametri di connessione nella sessione
                $dbConfig = [
                    'hostname' => $request->getVar('hostname'),
                    'username' => $request->getVar('username'),
                    'password' => $request->getVar('password'),
                    'database' => $request->getVar('database'),
                    'DBDriver' => 'MySQLi',
                    'port' => (int)($request->getVar('port') ?? 3306),
                    'prefix' => $request->getVar('prefix') ?? '',
                    'charset' => 'utf8',
                    'DBCollat' => 'utf8_general_ci'
                ];

                // $this->session->set('db_config', $dbConfig);

                // PRIMA: Aggiorna il file .env
                $this->updateEnvFile($dbConfig);

                // SECONDO: Reimposta i servizi per ricaricare la configurazione
                // \Config\Services::reset(true);

                $this->session->set('db_config', $dbConfig);

                // TERZO: Tenta la connessione al database
                try {
                    $db = \Config\Database::connect();
                    $db->initialize();

                    if ($db->connID) {
                        return redirect()->to(site_url('install/migrate'));
                    }
                } catch (\Exception $e) {
                    return view('install/database', [
                        'title' => 'Configurazione Database fallita',
                        'error' => $e->getMessage(),
                        'validation' => $this->validator
                    ]);
                }
            }

            return view('install/database', [
                'title' => 'Configurazione Database',
                'validation' => $this->validator
            ]);
        }

        return view('install/database', [
            'title' => 'Configurazione Database'
        ]);
    }

    public function migrate()
    {
        $request = \Config\Services::request();
        if (file_exists(WRITEPATH . 'installed.txt')) {
            return redirect()->to(base_url());
        }

        $dbConfig = $this->session->get('db_config');

        if (!$dbConfig) {
            return redirect()->to(site_url('install/database'));
        }

        if ($request->getMethod() == 'POST') {
            // Esegui migrazione
            try {
                // Aggiorna il file .env con le impostazioni del database
                $this->updateEnvFile($dbConfig);

                sleep(1); // Attendi 1 secondo per dare tempo al sistema di rilevare le modifiche

                // \Config\Services::reset(true); // Reset dei servizi

                // Esegui le migrazioni
                $migrate = \Config\Services::migrations();
                $migrate->setNamespace(null)->latest();

                // Esegui i seeder iniziali
                $seeder = \Config\Database::seeder();
                $seeder->call('InitialSeeder');

                // Crea l'account amministratore
                $adminData = [
                    'username' => $this->request->getPost('admin_username'),
                    'email' => $this->request->getPost('admin_email'),
                    'password' => password_hash($this->request->getPost('admin_password'), PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $db = \Config\Database::connect();
                $db->table('users')->insert($adminData);

                // Crea il file di installazione completata
                $data = [
                    'app_version' => $this->installer_version,
                    'installed_date' => date('Y-m-d H:i:s')
                ];

                write_file(WRITEPATH . 'installed.txt', json_encode($data));

                return redirect()->to(site_url('install/complete'));
            } catch (\Exception $e) {
                return view('install/migrate', [
                    'title' => 'Migrazione Database',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return view('install/migrate', [
            'title' => 'Migrazione Database'
        ]);
    }

    public function complete()
    {
        if (!file_exists(WRITEPATH . 'installed.txt')) {
            return redirect()->to(site_url('install'));
        }

        return view('install/complete', [
            'title' => 'Installazione Completata'
        ]);
    }

    private function updateEnvFile($dbConfig)
    {
        $envFile = ROOTPATH . '.env';
        $envContent = file_get_contents($envFile);

        // Array per tenere traccia di quali chiavi sono state aggiornate
        $updatedKeys = [];

        // Definisci le coppie chiave-valore
        $dbSettings = [
            'database.default.hostname' => $dbConfig['hostname'],
            'database.default.database' => $dbConfig['database'],
            'database.default.username' => $dbConfig['username'],
            'database.default.password' => $dbConfig['password'],
            'database.default.DBPrefix' => $dbConfig['prefix'],
            'database.default.port' => $dbConfig['port']
        ];

        // Dividi il file in linee
        $lines = explode("\n", $envContent);
        $newLines = [];

        // Esamina ogni linea e aggiorna quelle esistenti
        foreach ($lines as $line) {
            $updated = false;

            foreach ($dbSettings as $key => $value) {
                // Verifica se la linea contiene la chiave di configurazione, anche se commentata
                if (preg_match('/^\s*#?\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $newLines[] = "$key = $value";
                    $updatedKeys[] = $key;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                $newLines[] = $line;
            }
        }

        // Aggiungi le chiavi che non sono state trovate/aggiornate
        if (count($updatedKeys) < count($dbSettings)) {
            $newLines[] = "\n# Database";
            foreach ($dbSettings as $key => $value) {
                if (!in_array($key, $updatedKeys)) {
                    $newLines[] = "$key = $value";
                }
            }
        }

        // Verifica la presenza di CI_ENVIRONMENT, anche se commentato
        $hasEnvSetting = false;
        foreach ($newLines as &$line) {
            if (preg_match('/^\s*#?\s*CI_ENVIRONMENT\s*=/', $line)) {
                $line = 'CI_ENVIRONMENT = production';
                $hasEnvSetting = true;
                break;
            }
        }

        if (!$hasEnvSetting) {
            $newLines[] = "\nCI_ENVIRONMENT = production";
        }

        // Ricostruisci il contenuto del file
        $newEnvContent = implode("\n", $newLines);

        // Salva il nuovo contenuto nel file .env
        file_put_contents($envFile, $newEnvContent);
    }
}
