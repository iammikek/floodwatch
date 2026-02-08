<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key and organization. This will be
    | used to authenticate with the OpenAI API - you can find your API key
    | and organization on your OpenAI dashboard, at https://openai.com.
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Organization Admin Key (Usage API)
    |--------------------------------------------------------------------------
    |
    | Optional. The Usage API (admin dashboard LLM cost) requires an Admin API key
    | (sk-admin-...). Create at platform.openai.com/settings/organization/admin-keys.
    | Supports OPENAI_ORG_ADMIN_KEY or OPENAI_ORG_API_KEY.
    |
    */

    'org_api_key' => env('OPENAI_ORG_ADMIN_KEY') ?: env('OPENAI_ORG_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default model to use for chat completions (e.g. gpt-4o, gpt-4o-mini).
    */

    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Project
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API project. This is used optionally in
    | situations where you are using a legacy user API key and need association
    | with a project. This is not required for the newer API keys.
    */
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API base URL used to make requests. This
    | is needed if using a custom API endpoint. Defaults to: api.openai.com/v1
    */
    'base_uri' => env('OPENAI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
];
