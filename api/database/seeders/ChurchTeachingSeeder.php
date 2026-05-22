<?php

namespace Database\Seeders;

use App\Infrastructure\Teachings\Persistence\Mongo\Models\ChurchTeachingModel;
use Illuminate\Database\Seeder;

class ChurchTeachingSeeder extends Seeder
{
    public function run(): void
    {
        ChurchTeachingModel::truncate();

        ChurchTeachingModel::insert([
            ...$this->johnTeachings(),
            [
                'book' => 'genesis',
                'chapter' => 1,
                'verse' => 1,
                'language' => 'en',
                'version' => 'cpdv',
                'summary' => 'Genesis 1:1 supports the doctrine that God freely created all things.',
                'details' => 'Catholic teaching reads creation as ordered, good, and dependent on God, while distinguishing the Creator from creation.',
                'tradition' => 'Catholic',
                'references' => ['CCC 279', 'CCC 290'],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function johnTeachings(): array
    {
        return [
            $this->john(1, 'John 1 is central to Catholic teaching on the Incarnation: the eternal Son truly becomes man without ceasing to be God.', 'The chapter supports doctrines of Christ\'s divinity, creation through the Word, grace, truth, divine adoption, and the Church\'s witness to Christ. Catholic theology reads the Word made flesh as the foundation for sacramental and ecclesial life.', ['CCC 241', 'CCC 454', 'CCC 461-463', 'CCC 1996']),
            $this->john(2, 'John 2 is often read through the signs of Cana and the temple: Christ blesses marriage and reveals himself as the new temple.', 'Catholic teaching sees Cana as a sign of Christ\'s presence at marriage and the temple saying as a pointer to his death and resurrection. The chapter also shows Mary\'s intercessory role in directing servants to Christ.', ['CCC 486', 'CCC 547', 'CCC 583-586', 'CCC 1613']),
            $this->john(3, 'John 3 grounds Catholic teaching on baptismal rebirth, faith in Christ, and God\'s saving love.', 'The conversation with Nicodemus is linked to Baptism by water and the Spirit, while John 3:16 is read as a concentrated witness to the Father\'s love and the Son\'s saving mission. The chapter also joins belief with judgment and response to grace.', ['CCC 1215', 'CCC 1257', 'CCC 458', 'CCC 679']),
            $this->john(4, 'John 4 supports Catholic teaching on living water, universal mission, and worship in spirit and truth.', 'Jesus\' encounter with the Samaritan woman shows grace crossing ethnic and religious boundaries. Catholic interpretation connects living water with the Spirit and sees true worship fulfilled in Christ and carried by the Church\'s sacramental life.', ['CCC 694', 'CCC 728', 'CCC 1179', 'CCC 849']),
            $this->john(5, 'John 5 presents the Son as sharing the Father\'s work, authority, judgment, and life-giving power.', 'Catholic doctrine reads the chapter as a witness to Christ\'s divine sonship and final judgment. The healing controversy also shows that the Sabbath finds its deepest meaning in God\'s saving action in Christ.', ['CCC 590', 'CCC 594', 'CCC 679', 'CCC 2173']),
            $this->john(6, 'John 6 is a major Catholic text for the Eucharist, presenting Christ as the Bread of Life given for the life of the world.', 'Catholic teaching connects the feeding sign, manna background, and Bread of Life discourse with the Real Presence and sacramental communion. The chapter also shows that Eucharistic faith requires trust in Christ\'s words.', ['CCC 1338', 'CCC 1355', 'CCC 1374', 'CCC 1391']),
            $this->john(7, 'John 7 points to Christ as the giver of living water and the source of the Spirit promised to believers.', 'Within the feast setting, Catholic teaching reads Jesus\' invitation to drink as an anticipation of the Spirit poured out through his glorification. The divided responses also show that faith requires discernment beyond appearances.', ['CCC 687', 'CCC 694', 'CCC 729', 'CCC 1832']),
            $this->john(8, 'John 8 emphasizes Christ as light, liberator from sin, revealer of truth, and the one who discloses divine identity.', 'Catholic teaching connects the chapter with mercy, conversion, the slavery of sin, and freedom through truth. Jesus\' statements about Abraham and divine identity are read within the Church\'s confession of his pre-existence.', ['CCC 430', 'CCC 545', 'CCC 1741', 'CCC 2466']),
            $this->john(9, 'John 9 teaches that Christ gives spiritual sight and that faith grows through witness under pressure.', 'Catholic interpretation sees the healing of the man born blind as a sign of illumination, conversion, and baptismal symbolism. The chapter warns against culpable spiritual blindness while honoring courageous confession of Christ.', ['CCC 588', 'CCC 1216', 'CCC 1428', 'CCC 1816']),
            $this->john(10, 'John 10 presents Jesus as the Good Shepherd who knows, gathers, protects, and lays down his life for the sheep.', 'Catholic teaching connects the Good Shepherd with Christ\'s pastoral care and the Church\'s shepherding office. The unity of the flock also supports the Church\'s mission toward visible unity under Christ.', ['CCC 553', 'CCC 754', 'CCC 764', 'CCC 874']),
            $this->john(11, 'John 11 proclaims Christ as the resurrection and the life and prepares the reader for the Paschal Mystery.', 'Catholic doctrine reads the raising of Lazarus as a sign of Jesus\' authority over death, a call to faith, and a foreshadowing of Christ\'s own Resurrection. It also supports Christian hope in the resurrection of the body.', ['CCC 994', 'CCC 1002', 'CCC 1004', 'CCC 1681']),
            $this->john(12, 'John 12 gathers Catholic themes of sacrificial love, royal messiahship, judgment, and the fruitfulness of the cross.', 'Mary\'s anointing honors Jesus before burial, the entry into Jerusalem reveals his kingship, and the grain of wheat image teaches that life comes through self-giving death. The chapter points toward Christ drawing all people to himself from the cross.', ['CCC 440', 'CCC 550', 'CCC 618', 'CCC 662']),
            $this->john(13, 'John 13 teaches humble service, Eucharistic love, betrayal, and the new commandment of charity.', 'Catholic tradition reads the washing of feet as a model of pastoral charity and purification by Christ. The new commandment forms Christian moral life around the love revealed in Jesus\' self-gift.', ['CCC 459', 'CCC 520', 'CCC 1337', 'CCC 1823']),
            $this->john(14, 'John 14 is foundational for teaching on Christ as the way to the Father and on the Holy Spirit as Paraclete.', 'Catholic doctrine receives the chapter as a strong witness to Trinitarian faith: the Son reveals the Father, the Spirit is sent to teach and remind, and believers are drawn into communion with God through Christ.', ['CCC 260', 'CCC 459', 'CCC 683', 'CCC 729']),
            $this->john(15, 'John 15 teaches union with Christ through the vine and branches, fruitful discipleship, charity, and perseverance.', 'Catholic spirituality sees abiding in Christ as the source of grace, mission, and moral fruit. The command to love one another shapes ecclesial life, while pruning imagery supports purification and growth in holiness.', ['CCC 755', 'CCC 787', 'CCC 1822-1829', 'CCC 1988']),
            $this->john(16, 'John 16 teaches the Spirit\'s guidance, conviction of the world, Christian endurance, and Easter joy.', 'Catholic teaching connects the Spirit of truth with the Church\'s reception of revelation and ongoing guidance. The promise that sorrow becomes joy is read through the death and Resurrection of Christ.', ['CCC 687', 'CCC 729', 'CCC 737', 'CCC 2615']),
            $this->john(17, 'John 17 supports Catholic teaching on Christ\'s priestly prayer, consecration, mission, and the unity of the Church.', 'Jesus prays for the apostles and future believers, grounding ecclesial unity in communion with the Father and Son. Catholic doctrine sees this unity as both spiritual and visibly missionary.', ['CCC 2746-2751', 'CCC 820', 'CCC 858', 'CCC 260']),
            $this->john(18, 'John 18 reveals Christ\'s kingship during the Passion and his free acceptance of suffering.', 'Catholic teaching reads Jesus before Pilate as a revelation that his kingdom is not worldly yet truly royal. His arrest and trial show obedience, truth, and the beginning of the redemptive Passion.', ['CCC 440', 'CCC 559', 'CCC 600', 'CCC 2471']),
            $this->john(19, 'John 19 is central for Catholic teaching on the cross, Mary, sacrificial redemption, and the birth of sacramental life from Christ.', 'The crucifixion reveals Jesus as king and priestly victim. The entrusting of Mary, the blood and water from Christ\'s side, and the fulfillment of Scripture are read as rich signs of the Church, Baptism, Eucharist, and redemption.', ['CCC 478', 'CCC 618', 'CCC 766', 'CCC 964']),
            $this->john(20, 'John 20 proclaims the Resurrection, apostolic witness, the gift of the Spirit, forgiveness of sins, and faith in the risen Lord.', 'Catholic teaching connects the risen Christ\'s breathing of the Spirit with the apostolic ministry of reconciliation. Thomas\' confession expresses the Church\'s faith in Jesus as Lord and God.', ['CCC 448', 'CCC 643-645', 'CCC 730', 'CCC 976']),
            $this->john(21, 'John 21 teaches pastoral mission, Peter\'s restoration, love for Christ, and the apostolic witness behind the Gospel.', 'Catholic interpretation sees Jesus\' threefold command to feed his sheep as a major Petrine text. The chapter links pastoral authority with love, martyrdom, and service rather than domination.', ['CCC 553', 'CCC 881', 'CCC 1551', 'CCC 2472']),
        ];
    }

    /**
     * @param array<int, string> $references
     * @return array<string, mixed>
     */
    private function john(int $chapter, string $summary, string $details, array $references): array
    {
        return [
            'book' => 'john',
            'chapter' => $chapter,
            'verse' => 1,
            'language' => 'en',
            'version' => 'cpdv',
            'summary' => $summary,
            'details' => $details,
            'tradition' => 'Catholic',
            'references' => $references,
        ];
    }
}
