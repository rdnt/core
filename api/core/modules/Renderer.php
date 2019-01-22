<?php

// Trait that handles blueprint-based page rendering
trait Renderer {

    // Protected title-related datamembers
    protected $name;
    protected $separator;
    protected $title;
    // Private page rendering datamembers
    protected $page;
    protected $blueprint;
    protected $content;

    protected $system_dirs;
    protected $asset_dirs;

    /**
     * Returns the page title
     *
     * @return string The page title
     */
    function getTitle() {
        return escape($this->title);
    }

    /**
     * Returns the current page's name
     *
     * @return string Current page's name
     */
    function getPage() {
        return $this->page;
    }

    /**
     * Formats the current page's title
     */
    function formatTitle() {
        $this->title = $this->name . " " . $this->separator . " " . $this->page;
    }

    /**
     * Overrides the current page path
     *
     * @param string $page The page path to force
     */
    function setCurrentPage($url) {
        //$pages = array_merge($this->pages, $this->errors);
        $error = $this->pages[$url];
        $this->current_page = $url;
        $this->page = $error->name;
        $this->content = $error->template;
        $this->blueprint = $error->blueprint;
        // Re-format the title since the page data was changed
        $this->formatTitle();
    }

    function throwError($code) {
        http_response_code($code);
        $this->setCurrentPage("/error/$code");
    }

    function findCurrentPage() {
        $folder = $this->getProjectFolder();
        //$pages = array_merge($this->pages, $this->errors);
        // Loop all pages
        foreach ($this->pages as $page) {
            // If URL starts with a hash it is a dropdown and index 3 is an
            // array with the dropdown items
            if (substr($folder . $page->url, 0, 1) === '#') {
                foreach ($page->children as $child) {
                    if ($this->getCurrentPage() === $child->url) {
                        $this->page = $child->name;
                        $this->content = $child->template;
                        $this->blueprint = $child->blueprint;
                    }
                }
            }
            else if ($this->getCurrentPage() === $folder . $page->url) {
                $this->page = $page->name;
                $this->content = $page->template;
                $this->blueprint = $page->blueprint;
            }
        }
    }

    // function pageExists($page) {
    //     return array_key_exists($page, $this->pages);
    // }

    // function includePage() {
    //     $path = $this->getRoot() . "/includes/blueprints/" . $this->blueprint . ".php";
    //     if (!file_exists($path)) {
    //         $path = $this->getRoot() . "/includes/blueprints/default.php";
    //         $this->log("RENDERER", "Page blueprint file $this->blueprint.php doesn't exist. Using default blueprint.");
    //     }
    //     $core = $this;
    //     require_once $path;
    // }



    function isEndpoint($actual_page) {
        $parameters = explode("/", $actual_page);
        array_shift($parameters);
        if ($parameters[0] == "api") {
            return true;
        }
        return false;
    }

    function serveEndpoint($location) {
        $path = $this->getRoot() . $location . ".php";
        if (file_exists($path)) {
            global $core;
            require $path;
        }
        else {
            $this->serveErrorPage(403, $location);
        }
    }

    function isPage($location) {
        if (array_key_exists($location, $this->pages)) {
            return true;
        }
        else {
            return false;
        }
    }

    function servePage($location) {
        $path = $this->getRoot() . "/includes/blueprints/" . $this->blueprint . ".php";
        if (file_exists($path)) {
            global $core;
            require_once $path;
        }
        else {
            $this->serveErrorPage(501, $location);
        }
    }


    function isAsset($location) {

        $flag = false;
        foreach ($this->asset_dirs as $path) {
            if (substr($location, 0, strlen($path)) === $path) {
                $flag = true;
            }
        }
        if ($flag) {
            return true;
        }
        else {
            return false;
        }
    }

    function serveAsset($location) {
        $path = $this->getRoot() . $location;
        if (file_exists($path)) {
            global $core;
            require $path;
        }
        else {
            $this->serveErrorPage(404, $location);
        }
    }


    function serveErrorPage($code, $location) {
        $this->throwError($code);
        $this->servePage($location);
    }



    /**
     * Renders a page based on its blueprint's format
     */
    function renderPage() {

        $this->findCurrentPage();
        // Acquire the first segment of the requested path
        $dir = $this->getProjectFolder();
        $url = substr($this->getCurrentPage(), strlen($dir));


        $location = strtok($url, '?');

        $accessible = $this->isAccessible($location);

        if (!$accessible or $location === "/api/") {
            //echo "not allowed, serving 403<br>";
            $this->serveErrorPage(403, $location);
        }
        else if ($this->isEndpoint($location)) {
            //echo "serving endpoint<br>";
            $this->serveEndpoint($location);
        }
        else if ($this->isAsset($location)) {
            //echo "serving asset<br>";
            $this->serveAsset($location);
        }
        else if ($this->isPage($location)) {
            //echo "serving page<br>";
            $this->servePage($location);
        }
        else {
            //echo "serving 404<br>";
            $this->serveErrorPage(404, $location);
        }















    }

    /**
     * Loads a component on the page's content
     *
     * @param string $component The component to load
     */
    function loadComponent($component) {
        $core = $this;
        require_once($this->getRoot() . "/includes/components/$component.php");
    }

    /**
     * Inserts the main content into the page
     */
    function loadContent() {
        // Create a variable variable reference to the shell object
        // in order to be able to access the shell object by its name and not
        // $this when in page context
        $core = $this;
        $path = $this->getRoot() . "/includes/pages/" . $this->content . ".php";
        if (file_exists($path)) {
            require_once $path;
        }
        else {
            $this->log("RENDERER", "Page content file $this->content.php doesn't exist.");
        }
    }

    /**
     * Echoes a formatted style include
     *
     * @param string $style The style filename
     */
    function loadStyle($style) {
        $style = escape($style);
        $project_dir = escape($this->getProjectFolder());
        $commit_hash = escape($this->getCommitHash());
        echo "<link href=\"$project_dir/css/$style?v=$commit_hash\" type=\"text/css\" rel=\"stylesheet\" media=\"screen\"/>\n";
    }

    /**
     * Echoes a formatted script tag
     *
     * @param string $script The script filename
     */
    function loadScript($script) {
        $script = escape($script);
        $project_dir = escape($this->getProjectFolder());
        $commit_hash = escape($this->getCommitHash());
        echo "<script src=\"$project_dir/js/$script?v=$commit_hash\"></script>\n";
    }

    /**
     * Queues a script to be included after the scripts component is loaded
     *
     * @param string $script The script filename
     */
    function queueScript($script) {
        $this->script_queue[] = $script;
    }

    /**
     * Echoes all the queued scripts as formatted script tags
     */
    function appendScripts() {
        foreach ($this->script_queue as $script) {
            $this->loadScript($script);
        }
    }

}

?>