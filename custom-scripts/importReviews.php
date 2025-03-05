<?php
use Magento\Framework\App\Bootstrap;
use Magento\Review\Model\Review;
use Magento\Review\Model\Rating;
use Magento\Review\Model\Rating\Option\VoteFactory;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$appState = $objectManager->get('Magento\Framework\App\State');
$appState->setAreaCode('adminhtml');

$reviewFactory = $objectManager->get('Magento\Review\Model\ReviewFactory');
$reviewResource = $objectManager->get(ReviewResource::class);
$voteFactory = $objectManager->get(VoteFactory::class);
$ratingFactory = $objectManager->get('Magento\Review\Model\RatingFactory');
$productRepository = $objectManager->get('Magento\Catalog\Api\ProductRepositoryInterface');

$csvDirectory = 'reviews/'; // Path to the directory containing CSV files
$files = glob($csvDirectory . "*.csv");

foreach ($files as $csvFile) {
    echo "Processing file: $csvFile\n";
    $handle = fopen($csvFile, 'r');
    fgetcsv($handle); // Skip header row
    $header = false;
    while (($data = fgetcsv($handle)) !== false) {
        if(!$header){
            $header = true;
            continue;
        }
        list($empty, $sku, $customerName, $customerEmail, $reviewTitle, $reviewDetail, $ratings, $status, $date) = $data;

        try {
            $product = $productRepository->get($sku);
            $productId = $product->getId();

            $review = $reviewFactory->create();
            $review->setEntityId(1) // Product review
            ->setEntityPkValue($productId)
                ->setStatusId($status === 'Approved' ? Review::STATUS_APPROVED : Review::STATUS_PENDING)
                ->setTitle($reviewTitle)
                ->setDetail($reviewDetail)
                ->setNickname($customerName)
                ->setCustomerId(null)
                ->setStoreId(1)
                ->setStores([1]);

            $review->save();
            $reviewResource->save($review);

            // Assign ratings
            $rating = $ratingFactory->create()->getCollection()->addFieldToFilter('rating_code', 'Rating')->getFirstItem();
            if ($rating->getId()) {
                $vote = $voteFactory->create();
                $vote->setReviewId($review->getId())
                    ->setRatingId($rating->getId())
                    ->setPercent($ratings * 20)
                    ->setValue($ratings);
                $vote->save();
            }
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            print_r($data);
            die;
        }
    }

    fclose($handle);
}

echo "All reviews imported successfully!\n";
