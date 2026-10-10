<?php

/*
| Form errors in Bangla, for the student side (sign-up, checkout, account,
| reviews, Q&A). The admin panel stays in English. Rules the site does not
| use fall back to English.
*/

return [
    'accepted' => ':attribute মেনে নিতে হবে।',
    'after' => ':attribute অবশ্যই :date-এর পরের হতে হবে।',
    'array' => ':attribute সঠিক নয়।',
    'between' => [
        'numeric' => ':attribute :min থেকে :max-এর মধ্যে হতে হবে।',
        'string' => ':attribute :min থেকে :max অক্ষরের মধ্যে হতে হবে।',
        'array' => ':attribute-এ :min থেকে :max টি থাকতে হবে।',
    ],
    'boolean' => ':attribute সঠিক নয়।',
    'confirmed' => 'দুইবার লেখা :attribute মেলেনি।',
    'current_password' => 'পাসওয়ার্ড সঠিক নয়।',
    'date' => ':attribute সঠিক তারিখ নয়।',
    'email' => 'সঠিক :attribute দিন।',
    'exists' => 'বাছাই করা :attribute পাওয়া যায়নি।',
    'file' => ':attribute একটি ফাইল হতে হবে।',
    'gt' => [
        'numeric' => ':attribute অবশ্যই :value-এর বেশি হতে হবে।',
    ],
    'image' => ':attribute একটি ছবি হতে হবে।',
    'in' => 'বাছাই করা :attribute সঠিক নয়।',
    'integer' => ':attribute একটি পূর্ণ সংখ্যা হতে হবে।',
    'lt' => [
        'numeric' => ':attribute অবশ্যই :value-এর কম হতে হবে।',
    ],
    'max' => [
        'numeric' => ':attribute :max-এর বেশি হতে পারবে না।',
        'file' => ':attribute :max কিলোবাইটের বেশি হতে পারবে না।',
        'string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।',
        'array' => ':attribute-এ :max টির বেশি থাকতে পারবে না।',
    ],
    'mimes' => ':attribute অবশ্যই :values ধরনের ফাইল হতে হবে।',
    'min' => [
        'numeric' => ':attribute কমপক্ষে :min হতে হবে।',
        'file' => ':attribute কমপক্ষে :min কিলোবাইট হতে হবে।',
        'string' => ':attribute কমপক্ষে :min অক্ষরের হতে হবে।',
        'array' => ':attribute-এ কমপক্ষে :min টি থাকতে হবে।',
    ],
    'numeric' => ':attribute একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute-এ কমপক্ষে একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute-এ বড় ও ছোট হাতের অক্ষর দুটোই থাকতে হবে।',
        'numbers' => ':attribute-এ কমপক্ষে একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute-এ কমপক্ষে একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute ফাঁস হওয়া পাসওয়ার্ডের তালিকায় আছে। অন্য একটি দিন।',
    ],
    'present' => ':attribute থাকতে হবে।',
    'regex' => ':attribute-এর গঠন সঠিক নয়।',
    'required' => ':attribute দিতে হবে।',
    'required_with' => ':values দিলে :attribute-ও দিতে হবে।',
    'string' => ':attribute লেখা হতে হবে।',
    'unique' => 'এই :attribute আগেই ব্যবহার হয়েছে।',
    'uploaded' => ':attribute আপলোড হয়নি।',
    'url' => ':attribute সঠিক লিংক নয়।',

    'attributes' => [
        'name' => 'নাম',
        'email' => 'ইমেইল',
        'password' => 'পাসওয়ার্ড',
        'current_password' => 'বর্তমান পাসওয়ার্ড',
        'phone' => 'মোবাইল নম্বর',
        'code' => 'কুপন কোড',
        'method' => 'পেমেন্টের মাধ্যম',
        'payment method' => 'পেমেন্টের মাধ্যম',
        'amount' => 'টাকার পরিমাণ',
        'sender_number' => 'যে নম্বর থেকে পাঠিয়েছেন',
        'number you paid from' => 'যে নম্বর থেকে পাঠিয়েছেন',
        'transaction_id' => 'ট্রানজেকশন আইডি',
        'transaction ID' => 'ট্রানজেকশন আইডি',
        'proof' => 'স্ক্রিনশট',
        'note' => 'নোট',
        'rating' => 'রেটিং',
        'comment' => 'মন্তব্য',
        'body' => 'লেখা',
        'serial' => 'সার্টিফিকেট নম্বর',
    ],
];
