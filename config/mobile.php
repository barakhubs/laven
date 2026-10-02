<?php

/*
 * Mobile app versions. Raise ANDROID_MIN_VERSION_CODE to the new build's versionCode when a
 * release must be installed: older apps are then blocked until they update.
 */
return [
    'android' => [
        // Oldest versionCode still allowed to use the API.
        'min_version_code'    => (int) env('ANDROID_MIN_VERSION_CODE', 1),
        'latest_version_name' => env('ANDROID_LATEST_VERSION_NAME', '1.0.0'),
        // Where the "Update now" button goes: the Play Store listing or a direct APK link.
        'update_url'          => env('ANDROID_UPDATE_URL', 'https://play.google.com/store/apps/details?id=ug.co.lavensolutions.app'),
        'message'             => env('ANDROID_UPDATE_MESSAGE', 'A new version of Laven Solutions is available. Please update to keep using the app.'),
    ],
];
