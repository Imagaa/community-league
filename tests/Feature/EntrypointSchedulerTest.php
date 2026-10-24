<?php

it('entrypoint menjalankan scheduler di background', function () {
    $source = file_get_contents(base_path('docker/entrypoint.sh'));

    expect($source)->toContain('schedule:work');
});
