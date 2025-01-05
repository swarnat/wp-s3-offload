<?php

namespace WPDMigration;

class AdminPage
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'wpd_migration_adminpage'));

        add_action('wp_ajax_wpdimport_process', array($this, "process_import"));
    }

    public function process_import()
    {
        require(__DIR__ . '/Import.php');
        $importer = new Import($_POST['importurl']);

        switch ($_POST['importstep']) {
            case 'environment':

                $importer->sendEnvironment(
                    $_POST['data'],
                );

                break;
            case 'packages':

                $importer->sendPackages(
                    $_POST['data'],
                );

                break;
            case 'database':

                $importer->sendDatabase($_POST["data"]);

                break;
        }

        wp_die();
    }

    public function wpd_migration_adminpage()
    {
        add_options_page('WPD Migrator', 'WPD Migrator', 'manage_options', 'wpd-migrator-manager', array($this, 'admin_options_page'));
    }

    private function getAvailableBackup()
    {
        $availableBackupsRaw = get_option('updraft_backup_history', array());

        $availableBackups = array();
        foreach ($availableBackupsRaw as $timestamp => $backup) {
            if (!empty($backup['db'])) {
                $availableBackups[] = [
                    'timestamp' => $timestamp,
                    'filename' => $backup['db'],
                ];
            }
        }

        return $availableBackups;
    }


    public function admin_options_page()
    {
        require(__DIR__ . '/Import.php');

        try {
            if (!empty($_POST['step'])) {
                switch ($_REQUEST['step']) {
                    case 'import':
                        $importer = new Import($_POST['importurl']);
                        $importer->checkImportKey();

                        $environment = [];
                        foreach ($_POST['environmentvariable'] as $variable => $_notused) {
                            if (defined($variable)) {
                                $environment[] = $variable;
                            }
                        }

                        $packages = [];
                        foreach ($_POST['packages'] as $slug => $packageData) {
                            if (defined($variable)) {
                                $packages[] = [
                                    'package' => $packageData['package'],
                                    'version' => $packageData['version']
                                ];
                            }
                        }

                        $backupFile = $_POST['backup'];
                        $importUrl = $_POST['importurl'];

                        require(__DIR__ . '/../templates/process-import.php');
                        return;
                        break;
                }
            }

            if (!empty($_POST['importurl'])) {

                $importer = new Import($_POST['importurl']);

                $importer->checkImportKey();
                $checkPackages = $importer->checkPackages();
                $backupFile = $_POST['backup'];

                $ignoreVars = [
                    'ABSPATH',
                    'WP_DEBUG',

                    'AUTH_KEY',
                    'SECURE_AUTH_KEY',
                    'LOGGED_IN_KEY',
                    'NONCE_KEY',
                    'AUTH_SALT',
                    'SECURE_AUTH_SALT',
                    'LOGGED_IN_SALT',
                    'NONCE_SALT',
                ];

                $transferEnvironments = [];
                foreach (['PLUGINS', 'THEMES'] as $type) {
                    foreach ($checkPackages[$type] as $package) {
                        if (!empty($package['match']['env'])) {
                            $transferEnvironments = array_merge($transferEnvironments, $package['match']['env']);
                        }
                    }
                }

                $constants = get_defined_constants();
                $configFileContent = file_get_contents(ABSPATH . '/wp-config.php');
                $matches = [];
                preg_match_all('/\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $configFileContent, $matches);

                $environmentVariables = [];

                foreach ($matches[1] as $envVar) {
                    if (strpos($envVar, 'DB_') === 0 || in_array($envVar, $ignoreVars) !== false) {
                        continue;
                    }

                    $environmentVariables[$envVar] = defined($envVar) && in_array($envVar, $transferEnvironments) !== false;
                }

                $importUrl = $_POST['importurl'];
                require(__DIR__ . '/../templates/checkpackages.php');
                return;
            } else {
                $availableBackups = $this->getAvailableBackup();

                $attachments = get_posts( array(        
                    'post_type' => 'attachment',
                    'posts_per_page' => 5,
                    'meta_query' => array(
                        array(
                            'key'     => 's3_public_url',
                            'compare' => 'NOT EXISTS'
                        )
                    )
                ) );

                if(!empty($attachments)) {
                    $attachmentError = true;
                } else {
                    $attachmentError = false;
                }

                require(__DIR__ . '/../templates/adminpage.php');
            }
        } catch (\Exception $exp) {
            $class = 'notice notice-error';
            $message = $exp->getMessage();

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        }
    }
}

$WPDMigrationAdminPage = new AdminPage();
