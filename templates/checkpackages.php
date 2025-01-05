<form method="POST" action="">
    <input type="hidden" name="backup" value="<?php echo $backupFile; ?>" />
    <input type="hidden" name="step" value="import" />
    <input type="hidden" name="importurl" value="<?php echo $importUrl; ?>" />
    <table class="wp-list-table widefat fixed striped table-view-list pages">
        <thead>
            <tr>
                <th>Plugin</th>
                <th style="width:100px">Version</th>
                <th>Author</th>
                <th>matched package</th>
                <th style="width:100px">matched version</th>
                <th>matched author</th>
                <th>environment variables</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (['PLUGINS', 'THEMES'] as $type) { ?>
                <?php foreach ($checkPackages[$type] as $package) {  ?>
                    <tr>
                        <td><strong><?php echo $package['slug']; ?></strong><br /><?php echo $package['name']; ?></td>
                        <td><?php echo $package['version']; ?></td>
                        <td><?php echo $package['author']; ?></td>
                        <?php if (!empty($package['match'])) { ?>
                            <?php if ($package['match']['custom'] == false) { ?>
                                <td>
                                    <input type="hidden" name="packages[<?php echo $package['slug']; ?>][package]" value="<?php echo $package['match']['package'] ?>">
                                    <?php echo $package['match']['package'] ?>
                                </td>
                                <td>
                                    <input type="hidden" name="packages[<?php echo $package['slug']; ?>][version]" value="<?php echo $package['match']['version'] ?>">
                                    <?php echo $package['match']['version'] ?>
                                </td>
                                <td><?php echo $package['match']['author'] ?></td>
                            <?php } else { ?>
                                <td><input type="text" name="packages[<?php echo $package['slug']; ?>][package]" required="required" value="<?php echo $package['match']['package'] ?>"></td>
                                <td><input type="text" name="packages[<?php echo $package['slug']; ?>][version]" style="width:90px" required="required" value="<?php echo $package['match']['version'] ?>"></td>
                                <td><?php echo $package['match']['author'] ?></td>
                            <?php } ?>
                            <td style="font-family:verdana;">
                                <?php if (!empty($package['match']['env'])) { ?>
                                    <?php foreach ($package['match']['env'] as $env) { ?>
                                        <?php if (defined($env)) { ?>
                                            <?php echo $env; ?><br />
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                        <?php } else { ?>
                            <td><input type="text" name="packages[<?php echo $package['slug']; ?>][package]"  value=""></td>
                            <td><input type="text" name="packages[<?php echo $package['slug']; ?>][version]" style="width:90px"  value=""></td>
                            <td></td>
                            <td></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    <br/>
    <hr />
    <h3>Transfer the following environment variables into deployment</h3>
    <ul style="font-family:verdana;">
        <?php foreach($environmentVariables as $variable => $checked) { ?>
            <li><label><input type="checkbox" name="environmentvariable[<?php echo $variable; ?>]" value="1" <?php if($checked) { echo 'checked="checked"'; } ?> />&nbsp;&nbsp;&nbsp;<?php echo $variable; ?></label></li>
        <?php } ?>
    </ul>

    <input type="submit" class="button button-primary" value="overwrite existing configuration and start migration" name="submit" />
</form>