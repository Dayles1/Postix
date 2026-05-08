<?php

namespace App\Http\Controllers;

use App\Models\MessageGroup;



class TestController extends Controller
{
    public function test()
    {
        $ids = [69,67,64];
        foreach ($ids as $id) {

            $group = MessageGroup::find($id);
            if (!$group) {
                continue;
            }
            $group->update([
                'status' => 'canceled',
            ]);
            $group->messages()->whereNotNull('sent_at')->update(['status' => 'sent']);

        }
    }
}
