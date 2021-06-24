<?php

namespace Modules\Article\Service;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Kalnoy\Nestedset\QueryBuilder;
use Modules\Article\Model\Article;
use Modules\Article\Model\ArticleClass;
use Modules\Tools\Model\ToolsTagsHas;

/**
 * 标签扩展
 */
class Blade
{

    /**
     * 文章分类
     * @param array $args
     * @return Builder[]|Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|\Kalnoy\Nestedset\Collection|QueryBuilder|QueryBuilder[]|ArticleClass|ArticleClass[]
     */
    public static function articleClass(array $args = [])
    {
        $params = [
            'id' => $args['id'] ?: 0,
            'limit' => $args['limit'] ?: 10,
            'model' => (string)$args['model'] ?: 1,
            'sub' => (int)$args['sub'],
            'parent' => (int)$args['parent'],
            'siblings' => (int)$args['siblings'],
        ];
        $data = new ArticleClass();

        if ($params['id']) {
            $data = $data->where(['model_id' => $params['model']]);
            if (is_array($params['id'])) {
                return $data->limit($params['limit'])->whereIn('class_id', $params['id'])->get();
            } else {
                return $data->limit($params['limit'])->where('class_id', $params['id'])->get();
            }
        }
        $data = $data->scoped(['model_id' => $params['model']]);

        if ($params['siblings']) {
            return $data->where('class_id', $params['siblings'])->first()->siblings()->withDepth()->get();
        }

        if ($params['parent']) {
            return $data->ancestorsAndSelf($params['parent']);
        }

        $data = $data->limit($params['limit']);

        if ($params['sub']) {
            $data = $data->where('class_id', $params['sub'])->first()->descendants()->get()->toTree();
        } else {
            $data = $data->get()->toTree();
        }
        return $data;
    }

    /**
     * 文章列表
     * @param array $args
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public static function article(array $args = [])
    {
        $params = [
            'sub' => $args['sub'] ?: 0,
            'class' => $args['class'] ?: 0,
            'limit' => (int)$args['limit'] ?: 10,
            'offset' => (int)$args['offset'] ?: 0,
            'model' => (string)$args['model'] ?: 1,
            'image' => (bool)$args['image'],
            'page' => (bool)$args['page'],
            'attr' => (int)$args['attr'],
            'keyword' => (string)$args['keyword'],
            'tag' => (string)$args['tag'],
            'sort' => (array)$args['sort']
        ];
        $data = new \Modules\Article\Model\Article();
        $data = $data->with('class');

        if ($params['model']) {
            $data = $data->where('model_id', $params['model']);
        }

        if (isset($args['image']) && $params['image']) {
            $data = $data->where('image', '<>', null);
        }

        if (isset($args['image']) && !$params['image']) {
            $data = $data->where('image', null);
        }

        if (isset($args['attr'])) {
            $data = $data->with('attribute');
            $data = $data->whereHas('attribute', static function ($query) use ($params) {
                $query->where((new \Modules\Article\Model\ArticleAttribute())->getTable() . '.attr_id', $params['attr']);
            });
        }

        if (isset($args['offset'])) {
            $data = $data->offset($params['offset']);
        }

        if ($params['sub']) {
            $ids = ArticleClass::find($params['sub'])->descendantsAndSelf($params['sub'])->pluck('class_id');
            $data->whereHas('class', function ($query) use ($ids) {
                $query->whereIn((new ArticleClass())->getTable() . '.class_id', $ids);
            });
        }

        if ($params['class']) {
            $data->whereHas('class', function ($query) use ($params) {
                if (is_array($params['class'])) {
                    $query->whereIn((new ArticleClass())->getTable() . '.class_id', $params['class']);
                } else {
                    $query->where((new ArticleClass())->getTable() . '.class_id', $params['class']);
                }
            });
        }

        if (isset($args['keyword'])) {
            $data = $data->whereRaw("MATCH(title,content) AGAINST(?  IN BOOLEAN MODE)", [$params['keyword']]);
        }

        if (isset($args['tag'])) {
            $data = $data->with('tagged')->withAnyTag($params['tag']);
        }

        if ($params['sort']) {
            $sorts = $params['sort'];
            if (!$params[0]) {
                $sorts = [$params['sort'][0] => $params['sort'][1]];
            }
            foreach ($sorts as $key => $vo) {
                if ($key === 'view') {
                    $data = $data->orderByWith('views', 'pv', $vo);
                }
                if ($key === 'id') {
                    $data = $data->orderBy('article_id', $vo);
                }
            }
        }

        if ($params['page']) {
            $data = $data->paginate($params['limit']);
        } else {
            $data = $data->limit($params['limit'])->get();
        }

        if ($params['keyword']) {
            $keyword = preg_replace('!\s+!', ' ', trim($params['keyword']));
            $keywords = explode(' ', $keyword);
            $data->map(function ($item) use ($keywords) {
                foreach ($keywords as $vo) {
                    $item->title = str_replace($vo, '<strong>' . $vo . '</strong>', $item->title);
                    $item->description = str_replace($vo, '<strong>' . $vo . '</strong>', $item->description);
                }
                return $item;
            });
        }
        return $data;
    }

    /**
     * 标签列表
     * @param array $args
     * @return Builder[]|Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection|ToolsTagsHas[]
     */
    public static function tags(array $args = [])
    {
        $params = [
            'limit' => $args['limit'] ?: 10,
            'sort' => (array)$args['sort']
        ];
        $data = new ToolsTagsHas();
        $data = $data->where('taggable_type', Article::class);
        $data = $data->with('tag');
        $data = $data->limit($params['limit']);
        if ($params['sort']) {
            $sorts = $params['sort'];
            if (!$params[0]) {
                $sorts = [$params['sort'][0] => $params['sort'][1]];
            }
            foreach ($sorts as $key => $vo) {
                if ($key === 'count') {
                    $data = $data->orderByWith('tag', 'count', $vo);
                }
                if ($key === 'view') {
                    $data = $data->orderBy('view', $vo);
                }
            }
        }
        return $data->get()->map(static function ($item) {
            return (object)[
                'name' => $item->tag_name,
                'count' => $item->tag->count,
                'view' => $item->view
            ];
        });
    }
}
