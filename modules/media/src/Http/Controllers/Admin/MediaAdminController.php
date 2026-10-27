<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaAdminController — کتابخانهٔ رسانه در پنل (/admin/media).
 */

namespace Modules\Media\Http\Controllers\Admin;

use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Media;
use Tavaan\Core\Facade\Facades\Session;

/**
 * MediaAdminController — فهرست/آپلود/حذف رسانه‌ها.
 *
 * همهٔ عملیات از Facade هسته (`Media`) انجام می‌شود — موتور media مسئول
 * اعتبارسنجی MIME/اندازه، dedup (hash) و ذخیره روی disk است (DOC-074).
 *
 * 🔒 ARCH-003: فقط Facadeهای هسته.
 *
 * @api 1.0
 * @see modules/media/README.md
 */
final class MediaAdminController
{
    /** GET /admin/media */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('media.view');

        $page = \max(1, (int) $request->query('page', 1));
        $search = \trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $mimePrefix = match ($type) {
            'image' => 'image/',
            'video' => 'video/',
            'audio' => 'audio/',
            'doc' => 'application/',
            default => '',
        };

        $result = Media::paginate($search, $mimePrefix, $page, 24);

        return view('media::admin.media-index', [
            'items' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 24,
            'search' => $search,
            'type' => $type,
            'ok' => Session::pull('media_admin_ok'),
            'errors' => Session::pull('media_admin_errors') ?? [],
        ]);
    }

    /** POST /admin/media — آپلود فایل جدید */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('media.upload');

        $file = $request->file('media_file');
        if ($file === null || !$file->isValid()) {
            Session::flash('media_admin_errors', ['media_file' => [trans('media::messages.upload_no_file')]]);

            return redirect('/admin/media');
        }

        try {
            Media::upload($file);
            Session::flash('media_admin_ok', trans('media::messages.uploaded'));
        } catch (ValidationException $e) {
            Session::flash('media_admin_errors', $e->errors);
        } catch (\Tavaan\Core\Exceptions\Http\HttpException $e) {
            Session::flash('media_admin_errors', ['media_file' => [trans($e->messageId)]]);
        }

        return redirect('/admin/media');
    }

    /** DELETE /admin/media/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('media.delete');

        $media = Media::find($id);
        if ($media === null) {
            throw new RouteNotFoundException(messageId: 'media::messages.not_found', context: ['id' => $id]);
        }

        Media::delete($media);
        Session::flash('media_admin_ok', trans('media::messages.deleted'));

        return redirect('/admin/media');
    }
}
