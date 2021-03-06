<?php

declare(strict_types=1);

namespace App\Controller;

use Studio24\Frontend\Cms\RestData;
use Studio24\Frontend\ContentModel\ContentModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Psr\SimpleCache\CacheInterface;

/**
 * Form controller
 */
class FormController extends AbstractController
{

    /**
     * Frameworks Rest API data
     *
     * @var RestData
     */
    protected $api;

    protected $client;

    public function __construct(CacheInterface $cache)
    {
        $this->api = new RestData(
            getenv('APP_API_BASE_URL'),
            new ContentModel(__DIR__ . '/../../config/content/content-model.yaml')
        );
        $this->api->setContentType('frameworks');
        $this->api->setCache($cache);
        $this->client = HttpClient::create();
    }

    /**
     * eSourcingTraining Form template
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Studio24\Frontend\Exception\ContentFieldException
     * @throws \Studio24\Frontend\Exception\ContentFieldNotSetException
     * @throws \Studio24\Frontend\Exception\ContentTypeNotSetException
     * @throws \Studio24\Frontend\Exception\FailedRequestException
     * @throws \Studio24\Frontend\Exception\PermissionException
     */
    public function eSourcingTraining(Request $request)
    {
        $this->api->setContentType('esourcing_dates');
        $this->api->setCacheKey($request->getRequestUri());

        // @todo At present need to pass fake ID since API method is intended to return one item with an ID, review this
        $results = $this->api->getOne(0);

        $data = ['esourcingDates' => $results];

        return $this->render('forms/31-esourcing-training.html.twig', $data);
    }

    public function thankYou(Request $request)
    {
        $data = [];

        return $this->render('forms/thank-you.html.twig', $data);
    }

    public function sendToSalesforce(Request $request)
    {

        $params = $request->request;

        // form data used for validation and to remember values when user submits form
        $formData = [
            'enquiryType' => $params->get('origin', null),
            'name' => $params->get('name', null),
            'email' => $params->get('email', null),
            'phone' => $params->get('phone', null),
            'company' => $params->get('company', null),
            'jobTitle' => $params->get('00Nb0000009IXEs', null),
            'postCode' => $params->get('post-code', null),
            'moreDetail' =>  $params->get('more-detail', null),
        ];
        // check if callback checkbox has been set and add to form data
        if ($params->get('00Nb0000009IXEg') == '1') {
            $formData['callback'] = $params->get('00Nb0000009IXEg', null);
        }

        // check if complaint type exists and add to form data
        if ($params->get('complaint')) {
            $formData['complaint'] = $params->get('complaint', null);
        }
        // check for submitted data
        if (!empty($formData)) {
            $formErrors = $this->validateForm($formData);

            // if there are errors re-render contact form with errors and form values
            if ($formErrors) {
                return $this->render('forms/22-contact.html.twig', [
                    'formErrors' => $formErrors,
                    'formData' => $formData,
                ]);
            } else {
                // send to salesforce
                $response = $this->client->request('POST', getenv('SALESFORCE_WEB_TO_CASE_URL'), [
                    // these values are automatically encoded before including them in the URL
                    'query' => $params->all(),
                ]);
                if (!is_null($params->get('debug'))) {
                    return new Response(
                        $response->getContent()
                    );
                } elseif ($params->get('origin') == 'Website - Enquiry') {
                    return $this->redirectToRoute('form_contact_thanks');
                } else {
                    return $this->redirectToRoute('form_contact_thanks_complaint');
                }
            }
        }
    }

    public function validateForm(array $data)
    {
        $errorMessages = [
            'nameErr' => [
                'errors' => [],
                'link' => '#name',
            ],
            'emailErr' => [
                'errors' => [],
                'link' => '#email',
            ],
            'phoneErr' => [
                'errors' => [],
                'link' => '#phone',
            ],
            'companyErr' => [
                'errors' => [],
                'link' => '#company',
            ],
            'jobTitleErr' => [
                'errors' => [],
                'link' => '#00Nb0000009IXEs',
            ],
            'postCodeErr' => [
                'errors' => [],
                'link' => '#post-code',
            ],
            'moreDetailErr' => [
                'errors' => [],
                'link' => '#more-detail',
            ],
        ];

        // validation

        // name
        if (empty($data['name'])) {
            $errorMessages['nameErr']['errors'][0] = 'Enter your name';
        }

        if (strlen($data['name']) > 80) {
            $errorMessages['nameErr']['errors'][0] = 'Name must be 80 characters or fewer';
        }

        // email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errorMessages['emailErr']['errors'][0] = 'Enter an email address in the correct format, like name@example.com';
        }

        if (strlen($data['email']) > 80) {
            $errorMessages['emailErr']['errors'][0] = 'Email address must be 80 characters or fewer';
        }

        // phone
        if (empty($data['phone'])) {
            $errorMessages['phoneErr']['errors'][0] = 'Enter a phone number';
        }
        if (strlen($data['phone']) > 20) {
            $errorMessages['phoneErr']['errors'][0] = 'Telephone number must be 20 characters or fewer';
        }

        // company
        if (empty($data['company'])) {
            $errorMessages['companyErr']['errors'][0] = 'Enter an organisation';
        }

        if (strlen($data['company']) > 80) {
            $errorMessages['companyErr']['errors'][0] = 'Organisation must be 80 characters or fewer';
        }

        // job title
        if (empty($data['jobTitle'])) {
            $errorMessages['jobTitleErr']['errors'][0] = 'Enter a job title';
        }

        if (strlen($data['jobTitle']) > 80) {
            $errorMessages['jobTitleErr']['errors'][0] = 'Job title must be 80 characters or fewer';
        }

        // postcode

        if (strlen($data['postCode']) > 100) {
            $errorMessages['postCodeErr']['errors'][0] = 'Postcode must be 100 characters or fewer';
        }

        // more detail
        if (empty($data['moreDetail'])) {
            $errorMessages['moreDetailErr']['errors'][0] = 'Enter more detail';
        }

        // loop through and check for errors
        foreach ($errorMessages as $type => $value) {
            if (!empty($errorMessages[$type]['errors'])) {
                return $errorMessages;
            }
        }

        return false;
    }
}
