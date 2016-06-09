<?php

namespace AppBundle\Command;

use Goutte\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AlertmeCheckCommand extends ContainerAwareCommand
{
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36';

    protected function configure()
    {
        $this
            ->setName('alertme:check')
            ->setDescription('Check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sendMail('hello');
        return;

        $startingUrl = $this->getContainer()->getParameter('starting_url');

        $client = new Client();

        $client->setServerParameter('HTTP_USER_AGENT', self::USER_AGENT);

        $client->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
        $client->setHeader('Accept-Encoding', 'gzip, deflate, sdch, br');
        $client->setHeader('Accept-Language', 'fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4');
        $client->setHeader('Connection', 'keep-alive');
        $client->setHeader('User-Agent', self::USER_AGENT);

        while (true) {
            $crawler = $client->request('GET', $startingUrl);
            $form    = $crawler->selectButton('Effectuer une demande de rendez-vous')->form();
            $crawler = $client->submit($form, ['condition' => 'on']);

            /** @var Request $request */
            $request = $client->getRequest();

            if ($startingUrl . '/1' === $request->getUri()) {
                $form = $crawler->selectButton('Etape suivante')->form();

                $client->submit($form, ['planning' => '9122']);
                $request = $client->getRequest();

                if ($startingUrl . '/3' === $request->getUri()) {
                    $output->writeln('Nothing found');
                    $durationSleep = rand(1, 5);
                } else {
                    $message = sprintf('I found this uri: %s', $request->getUri());
                    $output->writeln($message);
                    $this->sendMail($message);
                    $durationSleep = rand(30, 50);
                }

                $output->writeln(sprintf('Sleep for %ss', $durationSleep));
                sleep($durationSleep);
            }
        }
    }

    /**
     * @param string $body
     */
    protected function sendMail($body)
    {
        $to = explode(',', $this->getContainer()->getParameter('email_to'));

        $message = \Swift_Message::newInstance()
            ->setSubject('Hello from Alert-me')
            ->setFrom($this->getContainer()->getParameter('email_from'))
            ->setTo($to)
            ->setBody($body, 'text/plain');
        ;

        $result = $this->getContainer()->get('mailer')->send($message);
    }
}
