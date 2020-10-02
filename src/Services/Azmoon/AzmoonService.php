<?php

namespace Larapress\ECommerce\Services\Azmoon;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Larapress\ECommerce\Services\Product\IProductService;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;

class AzmoonService implements IAzmoonService
{
    /**
     * Undocumented function
     *
     * @param Product|int $product
     * @return array
     */
    public function getAzmoonDetails($product)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        $productId = $product->id;

        if (
            is_null($product) ||
            !isset($product->data['types']['azmoon']['file_id']) ||
            is_null($product->data['types']['azmoon']['file_id'])
        ) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $file = FileUpload::find($product->data['types']['azmoon']['file_id']);
        if (is_null($file)) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $user = Auth::user();

        $data = $product->data;
        $canSeeAnswerSheet = !isset($data['types']['azmoon']['answer_at']) || is_null($data['types']['azmoon']['answer_at']) ?
        true :
        $data['types']['azmoon']['answer_at'];
        if ($canSeeAnswerSheet !== true) {
            $now = Carbon::now();
            $release = Carbon::createFromFormat(config('larapress.crud.datetime-format'), $canSeeAnswerSheet);
            $canSeeAnswerSheet = $now > $release;
        }

        if (
            !isset($product->data['types']['azmoon']['details']) ||
            !isset($product->data['types']['azmoon']['can_see_answers']) ||
            !is_array($product->data['types']['azmoon']['details']) ||
            count($product->data['types']['azmoon']['details']) === 0 ||
            ($canSeeAnswerSheet && (!isset($data['types']['azmoon']['can_see_answers']) || !$data['types']['azmoon']['can_see_answers'])) ||
            (!$canSeeAnswerSheet && (isset($data['types']['azmoon']['can_see_answers']) && $data['types']['azmoon']['can_see_answers']))
        ) {
            $data['types']['azmoon']['can_see_answers'] = $canSeeAnswerSheet;
            $data['types']['azmoon']['details'] = $this->getAzmoonJSONFromFile($file, $canSeeAnswerSheet);
            $product->update([
                'data' => $data
            ]);
        }

        $product['user_history'] = $this->getAzmoonResultForUser($user->id, $productId);

        return $product;
    }

    /**
     * Undocumented function
     *
     * @param Product|int $product
     * @param int $index
     * @param boolean $answer
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function streamAzmoonFileAtIndex(Request $request, $product, $index, $answer = false)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        $productId = $product->id;

        if (
            is_null($product) ||
            !isset($product->data['types']['azmoon']['file_id']) ||
            is_null($product->data['types']['azmoon']['file_id']) ||
            !isset($product->data['types']['azmoon']['details'])
        ) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        if (!isset($product->data['types']['azmoon']['details'][$index][$answer ? 'a_file':'q_file'])) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        /** @var FileUpload */
        $file = FileUpload::find($product->data['types']['azmoon']['file_id']);
        if (is_null($file)) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        /** @var IProductService */
        $productService = app(IProductService::class);
        return $productService->checkProductAccess($request, $product, function($request, $product) use($index, $answer, $file) {
            /** @var IFileUploadService */
            $fileService = app(IFileUploadService::class);
            $dir = substr($file->path, 0, strrpos($file->path, '.', -1));
            $filename = $product->data['types']['azmoon']['details'][$index][$answer ? 'a_file':'q_file'];
            $link = new FileUpload([
                'storage' => $file->storage,
                'path' => $dir.'/'.$filename,
                'filename' => $filename,
                'mime' => 'application/image',
            ]);
            return $fileService->serveFile($request, $link);
        });
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param int $product
     * @return FormEntry
     */
    public function acceptAzmoonResultForUser(Request $request, IProfileUser $user, $product)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        $productId = $product->id;

        if (
            is_null($product) ||
            !isset($product->data['types']['azmoon']['file_id']) ||
            is_null($product->data['types']['azmoon']['file_id'])
        ) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $file = FileUpload::find($product->data['types']['azmoon']['file_id']);
        if (is_null($file)) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }
        $details = $this->getAzmoonJSONFromFile($file, true);

        $correct = 0;
        $errors = 0;
        $total = 0;
        $answers = $request->get('answers', []);
        foreach ($details as $q) {
            if (isset($q['q_file'])) {
                if (isset($answers[$total])) {
                    if ($q['answer'] == $answers[$total]) {
                        $correct ++;
                    } else {
                        $errors ++;
                    }
                }
                $total ++;
            }
        }

        $request->merge([
            'user_id' => $user->id,
            'product_id' => $productId,
            'answers' => $answers,
            'correct' => $correct,
            'errors' => $errors,
            'total' => $total,
            'percent' => ($correct * 3 - $errors) / ($total * 3),
            'percent_no_error' => $correct / $total,
        ]);
        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);
        return $service->updateUserFormEntryTag(
            $request,
            $user,
            config('larapress.ecommerce.lms.azmoon_result_form_id'),
            'azmoon-' . $productId
        );
    }

    /**
     * Undocumented function
     *
     * @param int $userId
     * @param int $productId
     * @return FormEntry|null
     */
    public function getAzmoonResultForUser($userId, $productId)
    {
        $entries = FormEntry::query()
            ->where('user_id', $userId)
            ->where('form_id', config('larapress.ecommerce.lms.azmoon_result_form_id'))
            ->where('tags', 'azmoon-' . $productId)
            ->first();

        return $entries;
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return array
     */
    public function getAzmoonJSONFromFile(FileUpload $upload, $indludeAnswers = false)
    {
        if (!isset($upload->data['answer_sheet'])) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }
        $dir = substr($upload->path, 0, strrpos($upload->path, '.', -1));
        $storage = Storage::disk($upload->storage);
        $content = $storage->get($dir . '/' . $upload->data['answer_sheet']);
        $answers = explode(PHP_EOL, $content);
        $indexer = 1;
        $details = [];
        foreach ($answers as $answer) {
            $entry = [
                'question' => $indexer,
            ];
            if ($indludeAnswers) {
                $entry['answer'] = $answer;
            }

            foreach ($upload->data['questions'] as $qname) {
                if (\Illuminate\Support\Str::startsWith($qname, 'q' . $indexer . '.')) {
                    $entry['q_file'] = $qname;
                }
            }
            foreach ($upload->data['answers'] as $aname) {
                if (\Illuminate\Support\Str::startsWith($aname, 'a' . $indexer . '.')) {
                    $entry['a_file'] = $aname;
                    $entry['has_answer'] = true;
                }
            }

            $details[] = $entry;
            $indexer++;
        }

        return $details;
    }
}
