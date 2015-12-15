<?php
/**
 * This file is part of the {@link http://amsl.technology amsl} project.
 *
 * @author Norman Radtke
 * @copyright Copyright (c) 2015, {@link http://ub.uni-leipzig.de Leipzig University Library}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
/*
 * OntoWiki deadline helper module
 *
 * A simple helper that looks for dates to come and displays them in a module
 *
 * @category OntoWiki
 * @package Extensions_Map
 */

class DeadlinehelperModule extends OntoWiki_Module
{
    public function getTitle()
    {
        return $this->_owApp->translate->_('Important dates');
    }

    public function shouldShow()
    {
        return true;
    }

    /**
     * ordering function for usort used in getContent()
     * orders first for date (oldest on top latest at bottom)
     * orders after for title (A on top Z at bottom)
     */
    public function cmp($a, $b)
    {
        $int = strcmp($a["value"], $b["value"]);
        if ($int == 0) {
            return strcmp($a["subjectTitle"], $b["subjectTitle"]);
        } else {
            return $int;
        }
    }

    /**
     * Get the map content
     */
    public function getContents()
    {
        $minDate = new DateTime("now");
        $maxDate = new DateTime("now");

        if (isset($this->_privateConfig->dayNegOffset->value)) {
            $dayNegOffset = intval($this->_privateConfig->dayNegOffset->value);
        } else {
            $dayNegOffset = 0;
        }

        if ($dayNegOffset <= 0) {
            $minDate->setDate(-9999, 1, 1);
        } else {
            $interval = DateInterval::createfromdatestring("-$dayNegOffset day");
            $minDate->add($interval);
        }

        if (isset($this->_privateConfig->dayOffset->value)) {
            $dayOffset = intval($this->_privateConfig->dayOffset->value);
        } else {
            $dayOffset = 0;
        }

        if ($dayOffset <= 0) {
            $maxDate->setDate(9999, 12, 31);
        } else {
            $interval = DateInterval::createfromdatestring("+$dayOffset day");
            $maxDate->add($interval);
        }

        $properties = array();
        if (isset($this->_privateConfig->properties)) {
            $properties = $this->_privateConfig->properties;
        }
        if (count($properties) > 0) {
            $n = 1;

            // Write query
            $sparql = 'SELECT ?subject ?property ?value WHERE {' . PHP_EOL;
            $sparql .= '?subject ?property ?value .' . PHP_EOL;
            $sparql .= 'FILTER (' . PHP_EOL;
            foreach ($properties as $setup) {
                if ($n > 1) {
                    $sparql .= '|| ' . PHP_EOL;
                }
                $sparql .= '?property = <' . $setup->property . '> ' . PHP_EOL;
                $n++;
            }
            $sparql .= ')' . PHP_EOL;
            $sparql .= '}' . PHP_EOL;
            $sparql .= 'ORDER BY ASC (?value)' . PHP_EOL;

            $results = $this->_owApp->selectedModel->sparqlQuery($sparql);

            if (empty($results)) {
                $this->view->noResult = true;
                return $this->render('deadlinehelper');
            }

            $data = array();
            $url = new OntoWiki_Url(array('controller' => 'resource', 'action' => 'properties'));

            $titleHelper = new OntoWiki_Model_TitleHelper($this->_owApp->selectedModel);
            $found = false;

            // keep future dates and drop past dates
            foreach ($results as &$property) {
                //$date = strtotime($property['value']);
                $date = new DateTime($property['value']);
                if ($date === null) {
                    continue;
                }

                $url->setParam('r', $property['subject']);

                if ($date > $minDate && $date < $maxDate) {
                    $data[] = array('subjectTitle' => $titleHelper->getTitle($property['subject']),
                        'subjectUrl' => (string)$url,
                        'predicateTitle' => $titleHelper->getTitle($property['property']),
                        'value' => $property['value']);
                    $found = true;
                }
            }
            usort($data, array('DeadlinehelperModule', 'cmp'));
            
            $newData = array();
            $tabnames = array();
            $amounts = array();
            foreach ($properties as $prop) {
                if ($prop->property != 'http://example.org/remindeDate') {
                    $tabnames[] = $titleHelper->getTitle($prop->property);
                }
            }

            foreach ($tabnames as $prop) {
                $amounts[$prop] = 0;
            }

            foreach ($data as $part) {
                $name3 = $part['predicateTitle'];
                $newData[$name3][$part['value']][] = $part;
                $amounts[$name3] = $amounts[$name3] + 1;
                if (in_array($name3, $tabnames)) {
                    unset($tabnames[array_search($name3, $tabnames)]);
                }
            }



            if ($found === true) {
                $this->view->properties = $newData;
                $this->view->emptytabs = $tabnames;
                $this->view->amounts = $amounts;
            } else {
                $this->view->noResult = true;
            }
            return $this->render('deadlinehelper');
        }
    }
}

