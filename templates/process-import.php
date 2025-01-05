<script>
    var importUrl = "<?php echo $importUrl; ?>";
    var environment = <?php echo json_encode($environment); ?>;
    var packages = <?php echo json_encode($packages); ?>;
    var backupFile = <?php echo json_encode($backupFile); ?>;

    function startImport() {
        jQuery('#progress').html('');
        run_environment();
    }

    function run_environment() {
        progress('Environment import - Start');

        jQuery.post(ajaxurl, {
            action: 'wpdimport_process',
            importurl: importUrl,
            importstep: 'environment',
            data: environment
        }).then(() => {
            progress('Environment import - Done');
            run_packages();
        })

    }

    function run_packages() {
        progress('Package import - Start');

        jQuery.post(ajaxurl, {
            action: 'wpdimport_process',
            importstep: 'packages',
            importurl: importUrl,
            data: packages
        }).then(() => {
            progress('Package import - Done');
            run_database();
        })
    }
    function run_database() {
        progress('Database import - Start');

        jQuery.post(ajaxurl, {
            action: 'wpdimport_process',
            importstep: 'database',
            importurl: importUrl,
            data: backupFile
        }).then(() => {
            progress('Database import - Done');
        })
    }

    function run_finish() {

        jQuery.post(ajaxurl, {
            action: 'wpdimport_process',
            importurl: importUrl,
            importstep: 'finish'
        }).then(() => {
            progress('Import done');
        })
    }

    function progress(log) {
        jQuery('#progress').append("<div>" + log + "</div>");
    }

</script>
<style>
    div#progress {
        padding:10px;
        border:2px solid #ccc;
        min-height:100px;
    }
</style>
<div style="background-color:#fff;border:1px solid #ccc;padding:10px;margin-top:20px;">
<h2>WPD Migration</h2>
<div id="progress"></div>
<button onclick="startImport()" class="button">Start import</button>
</div>