<?php

namespace App\Console\Commands;

use App\Infrastructure\Teachings\Persistence\Mongo\Models\ChurchTeachingModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ImportJohnCatholicTeachings extends Command
{
    protected $signature = 'teachings:import-john-catholic
        {--language=en : Language code to store}
        {--bible-version=cpdv : Bible version key to store}
        {--fresh : Delete existing John teachings for this language/version before importing}';

    protected $description = 'Import Catholic chapter-level teachings for the Gospel of John with hidden source metadata.';

    public function handle(): int
    {
        $language = strtolower((string) $this->option('language'));
        $version = strtolower((string) $this->option('bible-version'));
        $teachings = $this->teachings($language, $version);

        if ($this->option('fresh')) {
            $deleted = ChurchTeachingModel::where('book', 'john')
                ->where('language', $language)
                ->where('version', $version)
                ->delete();

            $this->info("Deleted $deleted existing John Catholic teachings.");
        }

        foreach ($teachings as $teaching) {
            ChurchTeachingModel::updateOrCreate(
                [
                    'book' => $teaching['book'],
                    'chapter' => $teaching['chapter'],
                    'verse' => $teaching['verse'],
                    'language' => $teaching['language'],
                    'version' => $teaching['version'],
                ],
                [
                    'summary' => $teaching['summary'],
                    'details' => $teaching['details'],
                    'tradition' => $teaching['tradition'],
                    'references' => [],
                    'sources' => $teaching['sources'],
                ]
            );
        }

        Cache::flush();
        $this->info('Imported '.count($teachings)." John Catholic teachings as language=$language version=$version.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachings(string $language, string $version): array
    {
        return [
            $this->john(1, $language, $version, 'The eternal Word truly becomes flesh, revealing the Father and giving grace so that believers may become children of God.', 'Catholic teaching reads John 1 as a concentrated witness to Christ as the eternal Son, Creator, Light, and incarnate Word. The chapter supports the doctrines of the Trinity, the Incarnation, divine revelation, grace, and sacramental life beginning in the witness of John the Baptist.', ['CCC 241', 'CCC 291', 'CCC 454', 'CCC 461-463', 'Dei Verbum 2-4'], 'https://www.studylight.org/commentaries/eng/hcc/john-1.html'),
            $this->john(2, $language, $version, 'At Cana Christ blesses marriage and manifests his glory; in the temple sign he points toward his death, resurrection, and the new worship centered on himself.', 'Catholic interpretation connects Cana with the dignity of marriage and Mary\'s maternal intercession. The cleansing of the temple and the saying about raising it in three days show that Christ himself fulfills the temple and reveals the Paschal Mystery.', ['CCC 486', 'CCC 547', 'CCC 583-586', 'CCC 1613'], 'https://www.studylight.org/commentaries/eng/hcc/john-2.html'),
            $this->john(3, $language, $version, 'Jesus teaches the necessity of rebirth by water and the Spirit, the Father\'s saving love, and faith in the lifted-up Son.', 'The Church reads the dialogue with Nicodemus as foundational for Baptism and life in grace. John 3 also joins faith, judgment, divine mercy, and moral response: salvation is God\'s gift in the Son, yet the human person must come to the light.', ['CCC 458', 'CCC 679', 'CCC 1215', 'CCC 1257'], 'https://www.studylight.org/commentaries/eng/hcc/john-3.html'),
            $this->john(4, $language, $version, 'Christ offers living water, reveals true worship in Spirit and truth, and opens the mission of salvation beyond ethnic and religious boundaries.', 'The Samaritan woman\'s encounter shows grace seeking the sinner and forming a witness. Catholic theology connects living water with the Holy Spirit, sees authentic worship fulfilled in Christ, and recognizes the Church\'s universal mission in the harvest imagery.', ['CCC 694', 'CCC 728', 'CCC 1179', 'CCC 849'], 'https://www.studylight.org/commentaries/eng/hcc/john-4.html'),
            $this->john(5, $language, $version, 'The Son shares the Father\'s work, gives life, judges with divine authority, and calls humanity to hear his voice.', 'John 5 is doctrinally important for Christ\'s divine sonship and authority. The healing on the Sabbath shows that God\'s saving work is fulfilled in Christ, while the discourse on judgment and resurrection supports Catholic teaching on final judgment and eternal life.', ['CCC 590', 'CCC 594', 'CCC 679', 'CCC 2173'], 'https://www.studylight.org/commentaries/eng/hcc/john-5.html'),
            $this->john(6, $language, $version, 'Christ is the Bread of Life who gives his flesh for the life of the world, drawing the faithful toward Eucharistic communion.', 'Catholic teaching reads the feeding sign, manna background, and Bread of Life discourse as central to Eucharistic doctrine. The chapter points to the Real Presence, sacramental communion, and the need to trust Christ even when his words are hard.', ['CCC 1338', 'CCC 1355', 'CCC 1374', 'CCC 1391'], 'https://www.studylight.org/commentaries/eng/hcc/john-6.html'),
            $this->john(7, $language, $version, 'Jesus reveals himself during the feast as the source of living water and anticipates the gift of the Holy Spirit after his glorification.', 'The chapter shows division before Christ and the need for right judgment. Catholic doctrine links the promised streams of living water with the Spirit, whose mission flows from Christ and animates the Church\'s witness.', ['CCC 687', 'CCC 694', 'CCC 729', 'CCC 1832'], 'https://www.studylight.org/commentaries/eng/hcc/john-7.html'),
            $this->john(8, $language, $version, 'Christ reveals mercy, calls sinners to conversion, proclaims himself the light of the world, and teaches that truth frees from slavery to sin.', 'Catholic teaching sees mercy and conversion together in this chapter. Jesus\' words about sin, truth, freedom, and his pre-existence deepen the Church\'s confession that he is both merciful Redeemer and divine Lord.', ['CCC 430', 'CCC 545', 'CCC 1741', 'CCC 2466'], 'https://www.studylight.org/commentaries/eng/hcc/john-8.html'),
            $this->john(9, $language, $version, 'The healing of the man born blind shows Christ as the giver of sight and faith, while exposing the danger of culpable spiritual blindness.', 'Catholic tradition often reads this sign through baptismal illumination and conversion. The healed man grows from obedience to confession, showing that faith matures through witness, trial, and worship of Christ.', ['CCC 588', 'CCC 1216', 'CCC 1428', 'CCC 1816'], 'https://www.studylight.org/commentaries/eng/hcc/john-9.html'),
            $this->john(10, $language, $version, 'Jesus is the Good Shepherd who knows, gathers, protects, and lays down his life for the sheep.', 'The Church reads John 10 as a major text for Christ\'s pastoral charity, ecclesial unity, and shepherding ministry. The one flock is gathered by the one Shepherd, and all pastoral authority in the Church must remain a service of his self-giving care.', ['CCC 553', 'CCC 754', 'CCC 764', 'CCC 874'], 'https://www.studylight.org/commentaries/eng/hcc/john-10.html'),
            $this->john(11, $language, $version, 'Christ reveals himself as the resurrection and the life, calling believers to hope in his victory over death.', 'The raising of Lazarus is a sign of Jesus\' divine authority and a preparation for the Paschal Mystery. Catholic doctrine connects this chapter with faith in the resurrection of the body and Christian hope at the hour of death.', ['CCC 994', 'CCC 1002', 'CCC 1004', 'CCC 1681'], 'https://www.studylight.org/commentaries/eng/hcc/john-11.html'),
            $this->john(12, $language, $version, 'The anointing, royal entry, and grain of wheat image reveal the fruitfulness of Christ\'s sacrificial death.', 'John 12 gathers themes of messianic kingship, worship, judgment, and redemptive suffering. Catholic teaching sees the cross as the place where Christ draws all people to himself and where disciples learn the law of self-giving love.', ['CCC 440', 'CCC 550', 'CCC 618', 'CCC 662'], 'https://www.studylight.org/commentaries/eng/hcc/john-12.html'),
            $this->john(13, $language, $version, 'At the Last Supper Christ teaches humble service, purification, Eucharistic love, and the new commandment of charity.', 'The washing of feet reveals the shape of Christian authority as service. Catholic moral and sacramental theology reads the chapter in light of Christ\'s self-gift, the call to charity, and the pastoral humility required of his disciples.', ['CCC 459', 'CCC 520', 'CCC 1337', 'CCC 1823'], 'https://www.studylight.org/commentaries/eng/hcc/john-13.html'),
            $this->john(14, $language, $version, 'Christ is the way to the Father and promises the Paraclete, who teaches, reminds, and keeps believers in divine communion.', 'John 14 is a rich Trinitarian chapter: the Son reveals the Father, the Spirit is sent in Christ\'s name, and love is shown through obedience. Catholic doctrine sees here the interior life of grace and the Spirit\'s guidance of the Church.', ['CCC 260', 'CCC 459', 'CCC 683', 'CCC 729'], 'https://www.studylight.org/commentaries/eng/hcc/john-14.html'),
            $this->john(15, $language, $version, 'The vine and branches teach that Christian fruitfulness comes from abiding in Christ and living his commandment of love.', 'Catholic spirituality reads this chapter as a doctrine of grace, perseverance, charity, and mission. Apart from Christ the disciple can do nothing; in communion with him, the Church bears fruit through holiness, love, and witness.', ['CCC 755', 'CCC 787', 'CCC 1822-1829', 'CCC 1988'], 'https://www.studylight.org/commentaries/eng/hcc/john-15.html'),
            $this->john(16, $language, $version, 'The Spirit of truth convicts, guides, and leads believers into the fullness of Christ\'s revelation amid suffering and Easter joy.', 'Catholic teaching connects this chapter with the Spirit\'s mission in the Church, the deepening reception of revelation, and the prayerful confidence of disciples who live between tribulation and Christ\'s victory.', ['CCC 687', 'CCC 729', 'CCC 737', 'CCC 2615'], 'https://www.studylight.org/commentaries/eng/hcc/john-16.html'),
            $this->john(17, $language, $version, 'Jesus\' priestly prayer grounds the Church\'s unity, mission, consecration in truth, and communion with the Father and the Son.', 'Catholic doctrine treats John 17 as a central text for ecclesial unity and apostolic mission. Christ prays not only for the apostles but for future believers, so the Church\'s visible and spiritual unity must serve the world\'s faith.', ['CCC 260', 'CCC 820', 'CCC 858', 'CCC 2746-2751'], 'https://www.studylight.org/commentaries/eng/hcc/john-17.html'),
            $this->john(18, $language, $version, 'In his arrest and trial, Christ freely enters the Passion and bears witness to a kingdom founded on truth rather than worldly power.', 'Catholic teaching sees Jesus before Pilate as the revelation of true kingship. His obedience and calm authority show that the Passion is not defeat but the free offering through which salvation is accomplished.', ['CCC 440', 'CCC 559', 'CCC 600', 'CCC 2471'], 'https://www.studylight.org/commentaries/eng/hcc/john-18.html'),
            $this->john(19, $language, $version, 'The crucifixion reveals Christ as king and priestly victim; Mary, the beloved disciple, and the blood and water point toward the Church\'s sacramental life.', 'John 19 is central for Catholic teaching on redemption, Mary\'s maternal role, and the Church born from Christ\'s pierced side. The fulfilled Scriptures and final self-offering display the depth of divine love.', ['CCC 478', 'CCC 618', 'CCC 766', 'CCC 964'], 'https://www.studylight.org/commentaries/eng/hcc/john-19.html'),
            $this->john(20, $language, $version, 'The risen Christ sends the apostles, gives the Spirit, grants the ministry of forgiveness, and calls believers to faith.', 'Catholic doctrine connects this chapter with the historical reality of the Resurrection, apostolic witness, the sacrament of reconciliation, and Thomas\' confession of Jesus as Lord and God.', ['CCC 448', 'CCC 643-645', 'CCC 730', 'CCC 976'], 'https://www.studylight.org/commentaries/eng/hcc/john-20.html'),
            $this->john(21, $language, $version, 'The risen Lord restores Peter, entrusts him with pastoral care, and links authority with love, service, and witness unto death.', 'Catholic interpretation sees the threefold command to feed Christ\'s sheep as a major Petrine passage. The chapter closes the Gospel by joining Eucharistic recognition, pastoral mission, martyrdom, and the reliability of apostolic testimony.', ['CCC 553', 'CCC 881', 'CCC 1551', 'CCC 2472'], 'https://www.studylight.org/commentaries/eng/hcc/john-21.html'),
        ];
    }

    /**
     * @param array<int, string> $catechismReferences
     * @return array<string, mixed>
     */
    private function john(
        int $chapter,
        string $language,
        string $version,
        string $summary,
        string $details,
        array $catechismReferences,
        string $haydockUrl
    ): array {
        return [
            'book' => 'john',
            'chapter' => $chapter,
            'verse' => 1,
            'language' => $language,
            'version' => $version,
            'summary' => $summary,
            'details' => $details,
            'tradition' => 'Catholic',
            'sources' => [
                [
                    'type' => 'commentary',
                    'title' => 'Haydock Catholic Bible Commentary',
                    'reference' => "John $chapter",
                    'url' => $haydockUrl,
                ],
                [
                    'type' => 'catechism',
                    'title' => 'Catechism of the Catholic Church',
                    'reference' => implode('; ', $catechismReferences),
                    'url' => 'https://www.vatican.va/archive/ccc/index.htm',
                ],
                [
                    'type' => 'magisterial_document',
                    'title' => 'Second Vatican Council, Dei Verbum',
                    'reference' => 'Dei Verbum 2-4, 18-19',
                    'url' => 'https://www.vatican.va/archive/hist_councils/ii_vatican_council/documents/vat-ii_const_19651118_dei-verbum_en.html',
                ],
            ],
        ];
    }
}
