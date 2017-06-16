<?php

namespace CommunityTranslation\Service;

use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\ErrorList\Error\Error;
use Concrete\Core\Http\Request;
use Punic\Unit;

class RateLimit
{
    /**
     * @var \Concrete\Core\Form\Service\Form
     */
    protected $form;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Application $app
     */
    public function __construct(Application $app, Request $request)
    {
        $this->form = $app->make('helper/form');
        $this->request = $request;
    }

    /**
     * Get the units of the time limit.
     *
     * @return array
     */
    public function getTimeWindowUnits()
    {
        return  [
            1 => tc('Unit', 'seconds'),
            60 => tc('Unit', 'minutes'),
            3600 => tc('Unit', 'hours'),
            86400 => tc('Unit', 'days'),
            604800 => tc('Unit', 'weeks'),
        ];
    }

    /**
     * Get the representation of a time window expressed in seconds.
     *
     * @param int $timeWindow
     * @param int $default the value to use if $timeWindow is not valid
     *
     * @return int[] Index 0: value, index 1: units
     */
    public function splitTimeWindow($timeWindow, $default = 3600)
    {
        $timeWindow = (int) $timeWindow;
        if ($timeWindow <= 0) {
            $timeWindow = (int) $default;
        }
        foreach (array_keys($this->getTimeWindowUnits()) as $u) {
            if (($timeWindow % $u) === 0) {
                $unit = $u;
            } else {
                break;
            }
        }

        return [
            (int) ($timeWindow / $unit),
            $unit,
        ];
    }

    /**
     * Parse a value and a unit and returns the seconds (or an Error instance in case of errors).
     *
     * @param int|mixed $value
     * @param int|mixed $unit
     * @param bool $required
     *
     * @throws UserException
     *
     * @return int|null|Error
     */
    public function joinTimeWindow($value, $unit, $required = false)
    {
        if (is_int($value)) {
            $v = $value;
        } elseif (is_string($value) && trim($value) !== '' && is_numeric(trim($value))) {
            $v = (int) $value;
        } else {
            $v = null;
        }
        if ($v === null) {
            if ($required) {
                throw new UserException(t('The value of the time window is missing'));
            }
            $result = null;
        } elseif ($v <= 0) {
            throw new UserException(t('The value of the time window must be greater than zero'));
        } else {
            if (is_int($unit)) {
                $u = $unit;
            } elseif (is_string($unit) && trim($unit) !== '' && is_numeric(trim($unit))) {
                $u = (int) $unit;
            } else {
                $u = null;
            }
            if ($u === null || !array_key_exists($u, $this->getTimeWindowUnits())) {
                throw new UserException(t('The value of the time window units is not valid'));
            }
            $result = $u * $v;
            if (!is_int($result)) {
                throw new UserException(t('The value of the time window units is too big'));
            }
        }

        return $result;
    }

    /**
     * Create the HTML for a rate limit.
     *
     * @param string $name
     * @param int|null $maxRequests
     * @param int $timeWindow
     *
     * @return string
     */
    public function getWidgetHtml($name, $maxRequests, $timeWindow)
    {
        list($timeWindowValue, $timeWindowUnit) = $this->splitTimeWindow($timeWindow);
        $html = '<div class="input-group" id="' . $name . '_container">';
        $html .= $this->form->number($name . '_maxRequests', $maxRequests, ['min' => '1']);
        $html .= '<span class="input-group-addon">' . tc('TimeInterval', 'requests every') . '</span>';
        $html .= $this->form->number($name . '_timeWindow_value', $timeWindowValue, ['min' => '1']);
        $html .= '<div class="input-group-btn">';
        $timeWindowUnits = $this->getTimeWindowUnits();
        $u = $this->form->getRequestValue($name . '_timeWindow_unit');
        if (isset($timeWindowUnits[$u])) {
            $timeWindowUnit = $u;
        }
        $html .= '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span id="' . $name . '_timeWindow_unitlabel">' . h($timeWindowUnits[$timeWindowUnit]) . '</span> <span class="caret"></span></button>';
        $html .= '<ul class="dropdown-menu dropdown-menu-right">';
        foreach ($timeWindowUnits as $unitValue => $unitName) {
            $html .= '<li><a href="#" data-unit-value="' . $unitValue . '" data-max-value="' . floor(PHP_INT_MAX / $unitValue) . '">' . h($unitName) . '</a></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        $html .= $this->form->hidden($name . '_timeWindow_unit', $timeWindowUnit);
        $html .= '</div>';
        $html .= <<<EOT
<script>
$(document).ready(function() {
    var twValue = $('#{$name}_timeWindow_value'), twUnitLabel = $('#{$name}_timeWindow_unitlabel'), twUnit = $('#{$name}_timeWindow_unit'), twUnitLinks = $('#{$name}_container a[data-unit-value]');
    twUnit
        .on('change', function() {
            var a = twUnitLinks.filter('[data-unit-value="' + twUnit.val() + '"]');
            twUnitLabel.text(a.text());
            twValue.attr('max', a.data('max-value'));
        })
        .trigger('change')
    ;
    twUnitLinks.on('click', function(e) {
        e.preventDefault();
        twUnit.val($(this).data('unit-value')).trigger('change');
    });
});
</script>
EOT
        ;

        return $html;
    }

    /**
     * Get the rate limit values from the values of the widget.
     *
     * @param string $name
     * @param int $defaultTimeWindow the value of the time window if the max requests is empty and the received time window is invalid
     *
     * @throws UserException
     *
     * @return array
     */
    public function fromWidgetHtml($name, $defaultTimeWindow = 3600)
    {
        $post = $this->request->request;

        $s = $post->get($name . '_maxRequests');
        $maxRequests = (is_scalar($s) && is_numeric($s)) ? (int) $s : null;
        if ($maxRequests !== null && $maxRequests <= 0) {
            throw new UserException(t('Please specify a positive integer for the maximum number of requests (or leave it empty)'));
        }
        try {
            $timeWindow = $this->joinTimeWindow($post->get($name . '_timeWindow_value'), $post->get($name . '_timeWindow_unit'), true);
        } catch (UserException $x) {
            if ($maxRequests === null) {
                $timeWindow = $defaultTimeWindow;
            } else {
                throw $x;
            }
        }

        return [$maxRequests, $timeWindow];
    }

    /**
     * Format a rate limit.
     *
     * @param int|null $maxRequests
     * @param int|null $timeWindow
     *
     * @return string
     *
     * @example: '2 requests every 1 hour'
     */
    public function describeRate($maxRequests, $timeWindow)
    {
        if ($maxRequests && $timeWindow) {
            list($value, $unit) = $this->splitTimeWindow($timeWindow);
            switch ($unit) {
                case 60:
                    $duration = Unit::format($value, 'duration/minute', 'long');
                    break;
                case 3600:
                    $duration = Unit::format($value, 'duration/hour', 'long');
                    break;
                case 86400:
                    $duration = Unit::format($value, 'duration/day', 'long');
                    break;
                case 604800:
                    $duration = Unit::format($value, 'duration/week', 'long');
                    break;
                default:
                    $duration = Unit::format($timeWindow, 'duration/second', 'long');
                    break;
            }
            $result = t2('%1$d request every %2$s', '%1$d requests every %2$s', $maxRequests, $duration);
        } else {
            $result = '';
        }

        return $result;
    }
}
