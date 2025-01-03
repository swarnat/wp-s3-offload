<p>You have <?php echo $missing_number; ?> media entries, which are still stored locally</p>

<?php if($missing_number > 0) { ?>
<button class="button" id="start_batch_migration">Start Migration into S3 Storage</button>
<?php } else { ?>
<div class="notice notice-success">
    Great! All you media files are stored remotely
</div>
<?php } ?>    

<div id="migration_log" style="display:none;" data-count="<?php echo $missing_number; ?>">
    <div id="progress_bar">
        <div id="progress_bar_bar"></div>
        <div id="progress_bar_percent">50%</div>
    </div>
    <div id="migration_protocol"></div>
</div>

<style>
    #progress_bar {
        border: 1px solid #000;
        height: 30px;
        margin-top: 10px;
        position: relative;
    }

    #progress_bar_bar {
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 50%;
        background-color: #99cc66;
        transition: width 1s;
    }

    #progress_bar_percent {
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        font-size: 20px;
        padding-top: 5px;
        text-align: center;
        text-shadow:
            1px 1px 0 #fff,
            -1px 1px 0 #fff,
            -1px -1px 0 #fff,
            1px -1px 0 #fff;
    }
</style>
