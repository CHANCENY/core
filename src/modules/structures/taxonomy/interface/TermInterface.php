<?php

namespace Simp\Core\modules\structures\taxonomy\interface;

interface TermInterface
{
    public function vid();

    public function id();

    public function getName();

    public function setTerm(string $name, string $vid);
}