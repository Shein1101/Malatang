-- database/add-drinks-mogumogu.sql  [Myat] (2026-11-17)
-- モグモグ (ナタデココ入りドリンク) 12種類 を ジュースに追加する。
-- 写真は assets/images/drinks/ にあります (git に入っています)。
-- phpMyAdmin で「Malatang」DB を選んで、1回だけ実行する。何回実行しても同じ結果になります。
-- (先に add-feature-batch.sql で juices テーブルを作っておくこと)
-- 値段・説明は お店が admin の「ジュース」で あとから変えられます。

INSERT INTO juices (name, description, price, image_url, is_available, sort_order)
SELECT d.name, d.descr, 250, CONCAT('../assets/images/drinks/mogumogu-', d.slug, '.jpg'), TRUE,
       (SELECT COALESCE(MAX(j2.sort_order), 0) FROM juices j2) + d.ord
FROM (
    SELECT 'モグモグ オレンジ'         AS name, 'ナタデココ入り・オレンジ味'         AS descr, 'orange'        AS slug,  1 AS ord UNION ALL
    SELECT 'モグモグ メロン',                  'ナタデココ入り・メロン味',                   'melon',                 2 UNION ALL
    SELECT 'モグモグ ストロベリー',            'ナタデココ入り・いちご味',                   'strawberry',            3 UNION ALL
    SELECT 'モグモグ ライチ',                  'ナタデココ入り・ライチ味',                   'lychee',                4 UNION ALL
    SELECT 'モグモグ グレープ',                'ナタデココ入り・ぶどう味',                   'grape',                 5 UNION ALL
    SELECT 'モグモグ ピーチ',                  'ナタデココ入り・もも味',                     'peach',                 6 UNION ALL
    SELECT 'モグモグ アップル',                'ナタデココ入り・りんご味',                   'apple',                 7 UNION ALL
    SELECT 'モグモグ グアバ',                  'ナタデココ入り・グアバ味',                   'guava',                 8 UNION ALL
    SELECT 'モグモグ パイナップル',            'ナタデココ入り・パイナップル味',             'pineapple',             9 UNION ALL
    SELECT 'モグモグ マンゴー',                'ナタデココ入り・マンゴー味',                 'mango',                10 UNION ALL
    SELECT 'モグモグ ブラックカラント',        'ナタデココ入り・カシス味',                   'blackcurrant',         11 UNION ALL
    SELECT 'モグモグ パッションフルーツ',      'ナタデココ入り・パッションフルーツ味',       'passion-fruit',        12
) AS d
WHERE NOT EXISTS (SELECT 1 FROM juices x WHERE BINARY x.name = BINARY d.name);
