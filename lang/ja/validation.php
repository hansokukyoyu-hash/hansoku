<?php

// 画面で使う検証ルールの日本語メッセージ
return [
    'after_or_equal' => ':attribute は :date 以降の日付にしてください。',
    'array' => ':attribute の形式が正しくありません。',
    'date' => ':attribute は正しい日付にしてください。',
    'email' => ':attribute は正しいメールアドレスにしてください。',
    'enum' => ':attribute の選択が正しくありません。',
    'exists' => ':attribute の選択が正しくありません。',
    'in' => ':attribute の選択が正しくありません。',
    'integer' => ':attribute は整数にしてください。',
    'max' => [
        'string' => ':attribute は :max 文字以内にしてください。',
        'numeric' => ':attribute は :max 以下にしてください。',
    ],
    'min' => [
        'numeric' => ':attribute は :min 以上にしてください。',
        'string' => ':attribute は :min 文字以上にしてください。',
    ],
    'required' => ':attribute を入力してください。',
    'string' => ':attribute は文字列にしてください。',
    'unique' => 'この :attribute は既に登録されています。',

    'attributes' => [
        'start' => '開始日',
        'end' => '終了日',
        'mode' => '集計モード',
        'format' => 'フォーマット',
        'period_start' => '期間の開始',
        'period_end' => '期間の終了',
        'views' => '閲覧数',
        'note' => 'メモ',
        'name' => '名前',
        'email' => 'メールアドレス',
        'role' => 'ロール',
        'brands' => '担当ブランド',
        'brand_id' => 'ブランド',
        'platform' => 'SNS',
        'input_method' => '取得方法',
        'sort_order' => '表示順',
    ],
];
