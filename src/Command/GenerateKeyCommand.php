<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Command;

use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'messenger-compress:generate-key', description: 'Generate a random 32-byte key (base64) for message encryption')]
final class GenerateKeyCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Key id to print in the sample configuration', 'v1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = KeyRing::generateEncodedKey();
        /** @var string $id */
        $id = $input->getOption('id');

        $output->writeln($key);
        if ($output->isVerbose()) {
            $io->newLine();
            $io->writeln('Add to .env.local (never commit it):');
            $io->writeln(\sprintf('  MESSENGER_ENCRYPTION_KEY_%s=%s', strtoupper($id), $key));
            $io->writeln('and to config/packages/mattleads_messenger_compress.yaml:');
            $io->writeln(\sprintf("  encryption: { enabled: true, keys: { %s: '%%env(MESSENGER_ENCRYPTION_KEY_%s)%%' } }", $id, strtoupper($id)));
        }

        return Command::SUCCESS;
    }
}
