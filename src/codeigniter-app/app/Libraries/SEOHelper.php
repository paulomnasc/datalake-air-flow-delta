<?php

namespace App\Libraries;

class SeoHelper
{
    private $title;
    private $description;
    private $keywords;
    private $image;
    private $url;

    public function __construct()
    {
        $this->title = "Meu Site";
        $this->description = "Descrição padrão do site.";
        $this->keywords = "jogos, interativos, estudo, memória, memorização ,conteúdos, aprender, tabelas, resumo, divertido";
        $this->image = base_url('assets/img/logo.jpeg');
        $this->url = base_url();
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function generateMetaTags()
    {
        return "
            <title>{$this->title}</title>
            <meta name='description' content='{$this->description}'>
            <meta name='keywords' content='{$this->keywords}'>
            <meta property='og:title' content='{$this->title}'>
            <meta property='og:description' content='{$this->description}'>
            <meta property='og:image' content='{$this->image}'>
            <meta property='og:url' content='{$this->url}'>
            <link rel='canonical' href='{$this->url}'>
        ";
    }
}