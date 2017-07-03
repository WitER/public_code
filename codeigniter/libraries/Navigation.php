<?php

class Navigation {
    private $_ci;
    private $_links;

    public function __construct()
    {
        $this->_ci =& get_instance();
        $this->_ci->load->model('user_model');
        $this->_ci->load->helper('url');
        $this->_ci->load->config('navigation');

        $this->_links = $this->_ci->config->config['navigation'];
        if (!empty($this->_ci->user_model->role)) {
            $this->_links = !empty($this->_links[$this->_ci->user_model->role]) ? $this->_links[$this->_ci->user_model->role] : array();
        }
    }

    public function getNavigation()
    {
        return !empty($this->_links) ? $this->_checkLinks($this->_links) : false;
    }

    private function _checkLinks($links)
    {
        foreach ($links as $key => $link) {
            if (!is_array($link['link'])) {
                $links[$key]['link'] = array(
                    $link['link']
                );
            }
            $links[$key]['active'] = false;
            foreach ($links[$key]['link'] as $uri) {
                $links[$key]['active'] = strpos($this->_ci->uri->uri_string(), $uri) !== false
                    ? true
                    : false;
                if ($links[$key]['active']) {
                    $links[$key]['link'] = $uri;
                    break;
                }
            }
            if (!$links[$key]['active']) {
                $links[$key]['link'] = $links[$key]['link'][0];
            }

            if (!empty($link['children'])) {
                $links[$key]['children'] = $this->_checkLinks($link['children']);
            }
            if (!empty($link['counter']) && is_callable($link['counter'])) {
                $links[$key]['counter'] = $link['counter']();
            }
            $links[$key]['access'] = true;
            if (!empty($link['access']) && is_callable($link['access'])) {
                $links[$key]['access'] = $link['access']();
            }
        }

        return $links;
    }
}
