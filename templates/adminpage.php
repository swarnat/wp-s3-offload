<?php if(!empty($attachmentError)) { ?>
    <div class="error notice" style="background-color:#d63638; color:white;">
        <p><strong>Not offloaded attachments</strong></p>
        <p>Please run WP S3 Media Plugin to offload your media files and come back to this page.</p>
    </div>

<?php return; 
} ?>

<div style="background-color:#fff;border:2px solid #ccc;padding:10px;margin-top:20px;">
    <form method="POST" action="?page=wpd-migrator-manager&step=2">
        <label>Import URL: 
            <input type="text" name="importurl" style="width:100%;" value="https://wpd1.hohndorf.club:8332/import/IMPORT-U09Z9XPRGNVHJQZBDO9GL95WUFB8IA4DXOXOOUIPP6FMX6XCHYWHAWL-END" />
        </label>
        <br/>
        <br/>
        <label>UpdraftPlus Database Backup for migration: 
            <select name="backup" style="width:100%;">
            <?php foreach($availableBackups as $backup) { ?>
                <option value="<?php echo $backup['filename']; ?>"><?php echo date('d.m.Y H:i', $backup['timestamp']); ?> - <?php echo $backup['filename']; ?></option>
            <?php } ?>
        </select></label>
        <br/>
        <br/>
        <input type="submit" name="submit" class="button button-primary" value="Check Plugins" onclick="setTimeout(() => { this.disabled = true }, 100)" /> (The process takes some time. Please be patient)
    </form>
</div>