<?php
namespace WPDMigration;

class Import {
    private $validServer;
    private $requiredVersion;
    private $importurl;

    const REQUIRED_VERSION = '0.0.1';

    public function __construct($importurl)
    {
        $serverParts = parse_url($importurl);

        $this->validServer = false;
        $this->requiredVersion = false;

        if(!empty($serverParts['host'])) {
            $check = file_get_contents('https://' . $serverParts['host'] . ':' . $serverParts['port'] . '/');
            
            if(!empty($check)) {
                $checkData = json_decode($check, true);

                if($checkData['software'] == 'wordpress-remote-deployment') {
                    $this->validServer = true;

                    if($checkData['version'] >= self::REQUIRED_VERSION) {
                        $this->requiredVersion = true;
                    } else {
                        throw new \Exception('You need at least version ' . self::REQUIRED_VERSION . ' of the server to work with this version of plugin');
                    }
                } else {
                    throw new \Exception('No valid Wordpress Remote Deployment Server');
                }
            } else {
                throw new \Exception('No valid Wordpress Remote Deployment Server');
            }
            
        }

        $this->importurl = 'https://' . $serverParts['host'] . ':' . $serverParts['port'] . $serverParts["path"];
    }

    public function checkPackages() {
        $availablePlugins = get_plugins();
        $availableThemes = wp_get_themes();

        // $response = file_get_contents($this->server . 'import/' . $this->importKey . '/check');

        $matches = [
            'PLUGINS' => [],
            'THEMES' => []
        ];
        $packages = [
            'PLUGINS' => [],
            'THEMES' => []
        ];
        foreach($availablePlugins as $filepath => $plugin) {
            $matches['PLUGINS'][dirname($filepath)] = [
                'slug' => dirname($filepath),
                'name' => $plugin['Name'],
                'version' => $plugin['Version'],
                'author' => $plugin['Author'],
            ];

            $packages['PLUGINS'][] = $matches['PLUGINS'][dirname($filepath)];
        }

        foreach($availableThemes as $slug => $plugin) {
            $matches['THEMES'][$slug] = [
                'slug' => $slug,
                'name' => $plugin['Name'],
                'version' => $plugin['Version'],
                'author' => $plugin['Author Name'],
            ];

            $packages['THEMES'][] = $matches['THEMES'][$slug];
        }

        $result = wp_remote_post($this->importurl . '/checkpackages', [
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($packages),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 60
        ]);

        $resultBody = json_decode($result["body"], true);

        foreach($resultBody['PLUGINS'] as $slug => $plugin) {
            $matches['PLUGINS'][$slug]['match'] = $plugin;
        }
        foreach($resultBody['THEMES'] as $slug => $plugin) {
            $matches['THEMES'][$slug]['match'] = $plugin;
        }

        return $matches;
    }

    public function sendEnvironment($environment) {
        $body = array(
            'environment' => []
        );
        foreach($environment as $constant) {
            $body["environment"][$constant] = constant($constant);
        }

        $result = wp_remote_post($this->importurl . '/import/environment', [
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($body),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 60
        ]);

        return $result;
    }
    public function sendPackages($packages) {
        $body = array(
            'packages' => []
        );

        foreach($packages as $package) {
            if(!empty($package['package'])) {
                $body["packages"][] = $package;
            }
        }

        $result = wp_remote_post($this->importurl . '/import/packages', [
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($body),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 60,
        ]);

        return $result;
    }
    public function sendDatabase($databaseFile) {

        $boundary = "BOUNDARY-STRING";

        $localFile = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . "updraft" . DIRECTORY_SEPARATOR . $databaseFile;

        if(is_file($localFile)) {
            $payload = '';
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="file' .
            '"; filename="database.sql.gz"' . "\r\n";     
            $payload .= "\r\n";
            $payload .= file_get_contents( $localFile );
            $payload .= "\r\n";           
            
            $payload .= '--' . $boundary . '--';

            $result = wp_remote_post($this->importurl . '/import/database', [
                'headers'     => array('Content-Type' => 'multipart/form-data; boundary=' . $boundary),
                'body'        => $payload,
                'method'      => 'POST',
                'data_format' => 'body',
                'timeout'     => 60,
            ]);
        }
        
    }

    public function start_import($packages, $environmentVariables, $backupFile) {
        $environment = [];
        foreach($environmentVariables as $variable => $_notused) {
            if(defined($variable)) {
                $environment[$variable] = constant($variable);
            }
        }

        $body = array(
            'environment' => $environment
        );

        $result = wp_remote_post($this->importurl . '/environment', [
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($body),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 60
        ]);

        echo '<pre>';
        var_dump($packages, $environment, $backupFile);
        exit();
    }

    public function checkImportKey() {
        $response = wp_remote_get($this->importurl . '/check');

        if($response == 'expire') {
            throw new \Exception('Import key expired');
        }

        return $response == 'ok';
    }

    public function isValidServer() {
        return $this->validServer;
    }
    public function haveRequiredVersion() {
        return $this->requiredVersion;
    }
}