<?php

it('menyajikan halaman syarat & ketentuan', function () {
    $this->get(route('legal.terms'))->assertOk();
});

it('menyajikan halaman kebijakan privasi', function () {
    $this->get(route('legal.privacy'))->assertOk();
});
