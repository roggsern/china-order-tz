<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CommerceChannelCode;
use Illuminate\Validation\Validator;

trait RejectsTzLocalChinaFreight
{
    protected function rejectTzLocalChinaFreight(Validator $validator, CommerceChannelCode $channelCode): void
    {
        if ($channelCode !== CommerceChannelCode::TzLocal) {
            return;
        }

        if ($this->exists('shipping_options')) {
            $validator->errors()->add(
                'shipping_options',
                'TZ_LOCAL products cannot have China shipping options.',
            );
        }

        foreach (['air_shipping_price', 'sea_shipping_price'] as $field) {
            if ($this->exists($field) && $this->input($field) !== null) {
                $validator->errors()->add(
                    $field,
                    'TZ_LOCAL products cannot have China freight prices.',
                );
            }
        }
    }
}
