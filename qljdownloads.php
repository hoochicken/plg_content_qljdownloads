<?php
/**
 * @package        plg_content_qljdownloads
 * @copyright      Copyright (C) 2022 ql.de All rights reserved.
 * @author         Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
defined('_JEXEC') or die ('Restricted Access');

jimport('joomla.plugin.plugin');


class plgContentQljdownloads extends JPlugin
{

    protected $tagStart = 'qljdownloads';
    protected $tagEnd = '}';
    protected $arrReplace = [];
    protected $arrAttributesAvailable = ['category', 'file', 'class', 'id', 'style', 'type', 'title', 'layout',];
    public $params;
    private $boolDebug = false;
    private array $category_path = [];
    private $db = null;

    const JDOWNLOADS_ROOT = 'ROOT';
    const JDOWNLOADS_FOLDERSEPARATOR = '/';

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    /**
     * onContentPrepare :: some kind of controller of plugin
     * @param $strContext
     * @param $objArticle
     * @param $objParams
     * @param int $numPage
     * @return bool
     * @throws Exception
     */
    public function onContentPrepare($strContext, &$objArticle, &$objParams, $numPage = 0)
    {
        //if search => ignore
        if ('com_finder.indexer' === $strContext) {
            return true;
        }

        $this->db = \Joomla\CMS\Factory::getDbo();
        $this->tagStart = $this->params->get('tag', $this->tagStart);
        $this->tagEnd = '}';

        //if no plg tag in article => ignore
        if (false === strpos($objArticle->text, '{' . $this->tagStart) && false === strpos($objArticle->text, '{' . $this->tagEnd . '}')) {
            return true;
        }

        $jdownloads_root = $this->params->get('jdownloads_root', '/jdownloads');

        //replace tags
        $objArticle->text = $this->replaceStartTags($objArticle->text, $jdownloads_root);
    }

    /**
     * replaces placeholder tags {qljdownloads ...} with actual html code
     * @param $strText
     * @return mixed
     * @internal param $text
     */
    private function replaceStartTags($strText, $jdownloads_root)
    {
        extract($this->getMatchesComplete($strText));
        if (is_null($complete) || 0 === count($complete)) return $strText;
        $pluginParams = $this->params;

        //iterate through matches
        foreach ($complete as $numKey => $strContent) {

            //get replacement array (written to class variable)
            $replace = $this->getAttributes($this->arrAttributesAvailable, $attributes[$numKey]);
            $replace += $this->getAttributesDoubleEqual($this->arrAttributesAvailable, $attributes[$numKey]);

            if (isset($replace['file'])) {
                $fileId = (int)$replace['file'];
                $files = $this->getJdownloadsByFileId($fileId);
                if (0 === count($files)) continue;
                foreach ($files as $k => $file) {
                    $file = $this->enrichFile($file, $pluginParams, $jdownloads_root);
                    $files[$k] = $file;
                }
                $replace['data'] = $files;
                //get html code
                $replace_string = $this->getHtml($numKey, $replace, $pluginParams->get('layout', 'default'));
                $strText = str_replace($strContent, $replace_string, $strText);
            }

            if (isset($replace['category'])) {
                $categoryId = (int)$replace['category'];
                $files = $this->getJdownloadsByCategory($categoryId);
                if (0 === count($files)) continue;
                foreach ($files as $k => $file) {
                    $file = $this->enrichFile($file, $pluginParams, $jdownloads_root);
                    $files[$k] = $file;
                }
                $replace['data'] = $files;
                //get html code
                $replace_string = $this->getHtml($numKey, $replace, $pluginParams->get('layout', 'default'));
                $strText = str_replace($strContent, $replace_string, $strText);
            }
        }

        //return text
        return $strText;
    }

    /**
     * @param $string
     * @return array
     */
    private function getMatchesComplete($string)
    {
        // get matches
        $arrMatches = $this->getMatches($string);

        //if no matches found (can't be, but just in case ...)
        if (0 === count($arrMatches) || !isset($arrMatches[0])) {
            return [];
        }

        // write into more beautiful variables
        $return = ['complete' => $arrMatches[0] ?? [], 'attributes' => $arrMatches[1] ?? []];
        return $return;
    }

    /**
     * @param $string
     * @return array
     */
    private function getMatches($string)
    {
        //get matches to {qljdownloads}
        $strRegex = '~{' . $this->tagStart . '(.*?)}~s';
        preg_match_all($strRegex, $string, $arrMatches);
        return $arrMatches;
    }

    /**
     * method to get attributes
     * @param $arrAttributesAvailable
     * @param $string
     * @return array
     */
    private function getAttributes($arrAttributesAvailable, $string)
    {
        $strSelector = implode('|', $arrAttributesAvailable);
        preg_match_all('~(' . $strSelector . ')="(.+?)"~s', $string, $arrMatches);
        if (!isset($arrMatches[0]) || !is_array($arrMatches[0]) || 0 === count($arrMatches[0])) return [];

        $arrAttributes = [];
        foreach ($arrMatches[0] as $k => $v) {
            if (isset($arrMatches[1][$k]) && isset($arrMatches[2][$k])) {
                $arrAttributes[$arrMatches[1][$k]] = $arrMatches[2][$k];
            }
        }
        return $arrAttributes;
    }

    /**
     * method to get attributes
     * @param $arrAttributesAvailable
     * @param $string
     * @return array
     */
    private function getAttributesDoubleEqual($arrAttributesAvailable, $string)
    {
        $strSelector = implode('|', $arrAttributesAvailable);
        preg_match_all('~(' . $strSelector . ')==([0-9]+)~s', $string, $arrMatches);
        if (!isset($arrMatches[0]) || !is_array($arrMatches[0]) || 0 === count($arrMatches[0])) return [];
        $arrAttributes = [];
        foreach ($arrMatches[0] as $k => $v) {
            if (isset($arrMatches[1][$k]) && isset($arrMatches[2][$k])) {
                $arrAttributes[$arrMatches[1][$k]] = $arrMatches[2][$k];
            }
        }
        return $arrAttributes;
    }

    /**
     * @param $intCounter
     * @param $arrData
     * @return string
     */
    private function getHtml($intCounter, $arrData, $layoutPlugin): string
    {
        // initiating variables for output
        $objParams = $this->params;
        extract((array)$arrData['data'] ?? []);
        $class = isset($class) ? $class : '';
        $id = isset($id) ? $id : 'qljdownloads' . $intCounter;
        $style = isset($style) ? $style : '';
        $type = isset($type) ? $type : '';
        $title = isset($title) ? $title : '';
        $layout = isset($layout) ? $layout : $layoutPlugin;
        $href = isset($href) ? $href : '';
        $files = [$arrData['data'] ?? []];

        // load into buffer, and return
        ob_start();
        $strPathLayout = $this->getLayoutPath($this->_type, $this->_name, $layout);
        include $strPathLayout;
        $strHtml = ob_get_clean();
        return $strHtml;
    }

    /**
     * @param $extType
     * @param $extName
     * @param $layout
     * @return string
     */
    private function getLayoutPath($extType = 'content', $extName = 'qljdownloads', $layout = 'default'): string
    {
        $strLayoutFile = !empty($layout) ? $layout : $this->params->get('layout', $layout);
        $strPathLayout = JPluginHelper::getLayoutPath($extType, $extName, $strLayoutFile);
        if (!file_exists($strPathLayout)) {
            $strPathLayout = JPluginHelper::getLayoutPath($extType, $extName, 'default');
            die($strPathLayout);
        }
        return $strPathLayout;
    }

    /**
     * method to get matches according to search string
     * @internal param string $text haystack
     * @internal param string $searchString needle, string to be searched
     */
    private function getStyles()
    {
        $numBorderWidth = $this->params->get('borderwidth');
        $strBorderColor = $this->params->get('bordercolor');
        $strBorderType = $this->params->get('bordertype');
        $strFontColor = $this->params->get('fontcolor');
        $numPadding = $this->params->get('padding');
        $numOpacity = $this->params->get('backgroundopacity');
        $strBackgroundColor = $this->getBgColor($this->params->get('backgroundcolor'), $numOpacity);

        $arrStyle = [];
        $arrStyle[] = '.qljdownloads {color:' . $strFontColor . '; border:' . $numBorderWidth . 'px ' . $strBorderType . ' ' . $strBorderColor . '; padding:' . $numPadding . 'px; background:' . $strBackgroundColor . ';}';
        $strStyle = implode("\n", $arrStyle);
        JFactory::getDocument()->addStyleDeclaration($strStyle);
    }

    /**
     *
     */
    private function includeScripts()
    {
        if (1 == $this->params->get('jquery')) {
            JHtml::_('jquery.framework');
        }
        JHtml::_('script', JUri::root() . 'media/plg_content_qljdownloads/js/qljdownloads.js');
        JHtml::_('stylesheet', JUri::root() . 'media/plg_content_qljdownloads/css/qljdownloads.css');
    }


    public function getJdownloadsCategories($catId = []): array
    {
        $catId = $this->cleanseAsArrayWithIntegers($catId);
        $query = $this->db->getQuery(true);
        $query->select('*');
        $query->from('#__jdownloads_categories');
        $query->where('published = 1');
        if (0 < count($catId)) $query->where(sprintf('id IN(%s)', implode(',', $catId)));
        $this->db->setQuery($query);
        return $this->db->loadObjectList();
    }

    public function getJdownloadsByCategory($catId): array
    {
        $catId = $this->cleanseAsArrayWithIntegers($catId);
        $query = $this->db->getQuery(true);
        $query->select('f.id, f.title, f.created, f.url_download, f.catid AS cat_id, c.title AS cat_title, c.cat_dir, c.alias AS cat_alias');
        $query->from('#__jdownloads_files f');
        $query->where('f.published = 1');
        $query->order('f.created DESC');
        if (0 < count($catId)) $query->where(sprintf('f.catid IN(%s)', implode(',', $catId)));
        $query->leftJoin('#__jdownloads_categories c', 'c.id = f.catid');
        $this->db->setQuery($query);
        return $this->db->loadObjectList();
    }

    public function getJdownloadsByFileId($fileId): array
    {
        $fileId = $this->cleanseAsArrayWithIntegers($fileId);
        $query = $this->db->getQuery(true);
        $query->select('f.id, f.title, f.created, f.url_download, f.catid AS cat_id, c.title AS cat_title, c.cat_dir, c.alias AS cat_alias');
        $query->from('#__jdownloads_files f');
        $query->where('f.published = 1');
        $query->order('f.created DESC');
        if (0 < count($fileId)) $query->where(sprintf('f.id IN(%s)', implode(',', $fileId)));
        $query->leftJoin('#__jdownloads_categories c', 'c.id = f.catid');
        $this->db->setQuery($query);
        return $this->db->loadObjectList();
    }

    private function cleanseAsArrayWithIntegers($value)
    {
        // $value = implode(',', $value);
        if (is_numeric($value)) $value = [$value];
        $value = array_filter($value, function ($item) {
            if (!is_numeric($item)) return false;
            return true;
        });
        array_walk($value, function (&$item) {
            $item = (int)$item;
        });
        return $value;
    }

    private function enrichFile($file, $params, $jdownloads_root = '/jdownloads')
    {
        $this->category_path = [];
        $file->category_path = $this->getCategoryPath($file->cat_id);
        krsort($file->category_path);
        $category_path = $file->category_path;
        $href = $this->getJdHref($jdownloads_root, $category_path, $file);
        $file->category_path = json_encode($file->category_path);
        $file->href = $href;
        $file->label = $this->getLabel($params->get('label_scheme', '{title} ({id})'), $file);
        $file->cat_label = $this->getLabel($params->get('cat_label_scheme', '{cat_title} ({cat_id})'), $file);
        $file->link = $this->getHtmlLink($file->href, $file->label, $params->get('target', '_blank'));
        return $file;
    }

    private function getHtmlLink($link, $label, $target = '_blank'): string
    {
        $targetAttr = !empty($target) ? sprintf('target="%s"', $target) : '';
        return sprintf('<a href="%s" %s>%s</a>', $link, $targetAttr, $label);
    }

    private function getLabel($labelScheme, $file): string
    {
        $placeholder = array_keys((array)$file);
        array_walk($placeholder, function (&$item) {
            $item = '{' . $item . '}';
        });
        $value = (array)$file;
        return str_replace($placeholder, $value, $labelScheme);
    }

    private function getCategoryPath(int $catId): array
    {
        if (0 === $catId || 1 === $catId) return $this->category_path;
        $cat = $this->getJdownloadsCategories([$catId]);
        if (0 === count($cat)) return $this->category_path;
        $cat = array_pop($cat);
        if (0 === $cat->id || 1 === $cat->id || self::JDOWNLOADS_ROOT === $cat->title) return $this->category_path;
        $this->category_path[] = $cat;
        return $this->getCategoryPath($cat->parent_id);
    }

    public function getJdHref(string $jdownloads_root, array $category_path, $file): string
    {
        $path = [];
        foreach ($category_path as $cat) {
            $path[] = $cat->cat_dir;
        }
        $cat_path = implode(self::JDOWNLOADS_FOLDERSEPARATOR, $path);
        return sprintf('%s/%s/%s', $jdownloads_root, $cat_path, $file->url_download);
    }
}
