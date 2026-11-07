<?php

namespace Shiri\Domain\Wiki\Models;

class Wiki
{
    public $id;

    public $title;

    public $author;

    public $created;

    public $projectId;

    public $category;

    public function __construct() {}
}
