<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var number $perc */
/* @var int $translatedThreshold */

if ($perc === 100) {
    $percClass = 'progress-bar-success';
} elseif ($perc >= $translatedThreshold) {
    $percClass = 'progress-bar-info';
} elseif ($perc > 0) {
    $percClass = 'progress-bar-warning';
} else {
    $percClass = 'progress-bar-danger';
}
if ($perc > 0 && $perc < 10) {
    $percClass .= ' progress-bar-minwidth1';
} elseif ($perc >= 10 && $perc < 100) {
    $percClass .= ' progress-bar-minwidth2';
}
?><div class="progress">
    <div class="progress-bar <?php echo $percClass; ?>" role="progressbar" style="width: <?php echo $perc; ?>%">
        <span><?php echo $perc; ?></span>
    </div>
</div><?php
