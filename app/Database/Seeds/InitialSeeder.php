<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        // Creazione tabella utenti se non esiste già
        if (!$this->db->tableExists('users')) {
            $this->db->query("CREATE TABLE `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(100) NOT NULL,
                `email` varchar(100) NOT NULL,
                `password` varchar(255) NOT NULL,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`),
                UNIQUE KEY `email` (`email`)
            )");
        }
        
        // Inserisci eventuali dati iniziali delle altre tabelle
        // Esempio di tabella impostazioni
        if (!$this->db->tableExists('settings')) {
            $this->db->query("CREATE TABLE `settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(50) NOT NULL,
                `setting_value` text DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            )");
            
            // Inserisci impostazioni di base
            $data = [
                [
                    'setting_key' => 'site_name',
                    'setting_value' => 'La mia applicazione'
                ],
                [
                    'setting_key' => 'site_description',
                    'setting_value' => 'Una applicazione CodeIgniter 4'
                ],
                [
                    'setting_key' => 'version',
                    'setting_value' => '1.0.0'
                ],
                [
                    'setting_key' => 'installation_date',
                    'setting_value' => date('Y-m-d H:i:s')
                ]
            ];
            
            foreach ($data as $item) {
                $this->db->table('settings')->insert($item);
            }
        }
    }
}
