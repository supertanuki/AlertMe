<?php

namespace AppBundle\Command;

use AppBundle\Sms\FreeMobile;
use Goutte\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AlertmeCheckCommand extends ContainerAwareCommand
{
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36';

    /** request interval in seconds */
    const REQUEST_INTERVAL = 60;

    /** addition for interval in seconds */
    const REQUEST_INTERVAL_ADDITION = 30;

    /** request interval in seconds in cas of success response */
    const SUCCESS_REQUEST_INTERVAL = 300;

    protected function configure()
    {
        $this
            ->setName('alertme:check')
            ->setDescription('Check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $sms = new FreeMobile($container->getParameter('free_key'), $container->getParameter('free_user'));
        //$output->writeln($this->sendSms($sms, "[AlertMe] Starting script..."));

        $result = $this->sendMail('[AlertMe] Starting script', 'Starting script...');
        $output->writeln($result);

        $startingUrl = $container->getParameter('starting_url');

        $client = new Client();

        $client->setServerParameter('HTTP_USER_AGENT', self::USER_AGENT);

        $client->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
        $client->setHeader('Accept-Encoding', 'gzip, deflate, sdch, br');
        $client->setHeader('Accept-Language', 'fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4');
        $client->setHeader('Connection', 'keep-alive');
        $client->setHeader('User-Agent', self::USER_AGENT);

        $iteration = 0;

        while (true) {
            $iteration++;

            $crawler = $client->request('GET', $startingUrl);
            $form    = $crawler->selectButton('Effectuer une demande de rendez-vous')->form();
            $crawler = $client->submit($form, ['condition' => 'on']);

            /** @var Request $request */
            $request = $client->getRequest();

            $step = 1;

            if (($startingUrl . '/' . $step) === $request->getUri()) {
                $form = $crawler->selectButton('Etape suivante')->form();

                $crawler = $client->submit($form, ['planning' => '9122']);
                $request = $client->getRequest();

                $step++;

                if (($startingUrl . '/' . $step) === $request->getUri()) {
                    $output->writeln('Nothing found');
                    $durationSleep = rand(self::REQUEST_INTERVAL, self::REQUEST_INTERVAL + self::REQUEST_INTERVAL_ADDITION);
                } else {
                    dump($crawler->html());
                    $message = sprintf('I found this uri: %s', $request->getUri());
                    $output->writeln($message);

                    $output->writeln($this->sendSms($sms, "[AlertMe] " . $message));

                    $result = $this->sendMail('[AlertMe] Found a page!', $message . "\n\n" . $crawler->html());
                    $output->writeln($result);

                    $durationSleep = rand(self::SUCCESS_REQUEST_INTERVAL, self::SUCCESS_REQUEST_INTERVAL + self::REQUEST_INTERVAL_ADDITION);
                }

                $output->writeln(sprintf('#%s - Sleep for %ss', $iteration, $durationSleep));
                sleep($durationSleep);
            }
        }
    }

    /**
     * @param FreeMobile $sms
     * @param string     $message
     *
     * @return string
     */
    protected function sendSms(FreeMobile $sms, $message)
    {
        try {
            $sms->send("[AlertMe] Starting script...");
            return 'Ok';
        } catch (\Exception $e) {
            return $e->getCode() . ': ' . $e->getMessage();
        }
    }

    /**
     * @param string $subject
     * @param string $body
     *
     * @return string
     */
    protected function sendMail($subject, $body)
    {
        return;

        $container = $this->getContainer();

        $emailTo = $container->getParameter('email_to');
        $to = explode(',', $emailTo);

        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($container->getParameter('email_from'))
            ->setTo($to)
            ->setBody($body, 'text/plain');
        ;

        /** @var \Swift_Mailer $mailer */
        $mailer = $container->get('mailer');

        if ($mailer->send($message)) {
            return sprintf('Mail sent to %s', $emailTo);
        } else {
            return sprintf('Impossible to send mail to %s', $emailTo);
        }
    }
}
