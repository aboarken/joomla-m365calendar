<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Service;

use Joomla\CMS\Component\Router\Rules\RulesInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Routes the feed task to /{menu-alias}/feed.ics.
 *
 * With "Add suffix to URLs" on, the core router appends ".ics" from the
 * format variable; otherwise the segment itself is "feed.ics".
 *
 * @since  1.0.0
 */
class FeedRule implements RulesInterface
{
    /**
     * Constructor.
     *
     * @param   boolean  $sefSuffix  Whether the site adds format suffixes.
     *
     * @since   1.0.0
     */
    public function __construct(private readonly bool $sefSuffix)
    {
    }

    /**
     * Prepare the query (nothing to do).
     *
     * @param   array  &$query  The query array to process.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function preprocess(&$query)
    {
    }

    /**
     * Build the feed segment.
     *
     * @param   array  &$query     The vars that should be converted.
     * @param   array  &$segments  The URL segments to create.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function build(&$query, &$segments)
    {
        if (($query['task'] ?? '') !== 'feed.serve' || empty($query['Itemid'])) {
            return;
        }

        unset($query['task'], $query['view']);

        if ($this->sefSuffix) {
            $segments[]      = 'feed';
            $query['format'] = 'ics';
        } else {
            $segments[] = 'feed.ics';
            unset($query['format']);
        }
    }

    /**
     * Parse the feed segment.
     *
     * @param   array  &$segments  The URL segments to parse.
     * @param   array  &$vars      The vars that result from the segments.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function parse(&$segments, &$vars)
    {
        if (\count($segments) !== 1 || !\in_array($segments[0], ['feed', 'feed.ics'], true)) {
            return;
        }

        array_shift($segments);

        $vars['task']   = 'feed.serve';
        $vars['format'] = 'ics';
    }
}
