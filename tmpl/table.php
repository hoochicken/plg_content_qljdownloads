<?php
/**
 * @package        PLG_CONTENT_QLJDOWNLOADS_
 * @copyright    Copyright (C) 2022 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerStyle('plg_content_qljdownloads', 'plg_content_qljdownloads/styles.css');
$wa->useStyle('plg_content_qljdownloads');

/** @var array $files [stClass] */
/** @var string $jdownloads_root */
/** @var JRegistry $objParams */
?>

<div class="qljdownloads">
    <table class="table table-striped table-hover">
        <thead>
        <tr>
            <th>#</th>
            <th><?php echo Text::_('PLG_CONTENT_QLJDOWNLOADS_TITLE'); ?></th>
            <?php if ((bool)$objParams->get('cat_column_id_show', 1)): ?>
                <th><?php echo Text::_('PLG_CONTENT_QLJDOWNLOADS_CATEGORY'); ?></th>
            <?php endif; ?>
            <th><?php echo Text::_('PLG_CONTENT_QLJDOWNLOADS_CREATED'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($files as $file) : ?>
            <tr>
                <td>
                    <?php echo $file->id; ?>
                </td>
                <td><span class="title"><?php echo $file->link; ?></span></td>
                <?php if ((bool)$objParams->get('cat_column_show', 1)): ?>
                    <td><?php echo $file->cat_label; ?></td>
                <?php endif; ?>
                <td><?php echo $file->created; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>