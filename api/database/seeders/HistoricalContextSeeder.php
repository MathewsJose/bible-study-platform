<?php

namespace Database\Seeders;

use App\Infrastructure\History\Persistence\Mongo\Models\HistoricalContextModel;
use Illuminate\Database\Seeder;

class HistoricalContextSeeder extends Seeder
{
    public function run(): void
    {
        HistoricalContextModel::truncate();

        HistoricalContextModel::insert([
            ...$this->johnContexts(),
            [
                'book' => 'genesis',
                'chapter' => 1,
                'verse' => 1,
                'language' => 'en',
                'version' => 'cpdv',
                'summary' => 'Genesis 1:1 opens the creation account by identifying God as the creator of heaven and earth.',
                'details' => 'The verse introduces the biblical narrative with a theological claim about creation\'s origin and dependence on God.',
                'references' => ['Genesis 1:1-2:4'],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function johnContexts(): array
    {
        return [
            $this->john(1, 'John opens with a prologue that presents Jesus as the eternal Word, then moves to John the Baptist and the first disciples.', 'The chapter uses Jewish creation language, wisdom imagery, and witness testimony to introduce the Gospel\'s central claim that God is revealed in the Word made flesh. The opening discipleship scenes are set around the Jordan and Galilee.', ['John 1:1-18', 'Genesis 1:1', 'John 1:19-51']),
            $this->john(2, 'John 2 joins the sign at Cana with Jesus cleansing the Jerusalem temple.', 'Cana introduces the Gospel\'s sign pattern, where visible acts disclose Jesus\' identity. The temple scene places Jesus in conflict with Jerusalem authorities and connects his body with the future temple of God\'s presence.', ['John 2:1-12', 'John 2:13-25', 'Psalm 69:9']),
            $this->john(3, 'John 3 records Jesus speaking with Nicodemus about new birth, heavenly testimony, and God sending the Son.', 'The setting draws on Pharisaic learning, Jewish purification language, and the imagery of Moses lifting up the serpent. The chapter moves from private dialogue to public testimony about belief, judgment, and divine love.', ['John 3:1-21', 'Numbers 21:4-9', 'John 3:22-36']),
            $this->john(4, 'John 4 follows Jesus through Samaria, where he speaks with a Samaritan woman and later heals an official\'s son.', 'The Samaritan setting reflects centuries of tension over worship, ancestry, and holy places. The dialogue at Jacob\'s well expands the mission beyond Judea and highlights worship in spirit and truth.', ['John 4:1-42', '2 Kings 17:24-41', 'John 4:46-54']),
            $this->john(5, 'John 5 centers on a healing at the pool near the Sheep Gate and the controversy that follows.', 'The Sabbath dispute is not only about healing but about Jesus\' claim to act with the Father\'s authority. The chapter includes legal-style testimony from John the Baptist, the works of Jesus, the Father, and Scripture.', ['John 5:1-47', 'Deuteronomy 19:15', 'Exodus 20:8-11']),
            $this->john(6, 'John 6 contains the feeding of the multitude, Jesus walking on the sea, and the Bread of Life discourse.', 'The chapter is set around Passover and evokes Exodus themes: manna, wilderness feeding, sea crossing, and divine provision. The discourse in Capernaum becomes a turning point as many disciples struggle with Jesus\' words.', ['John 6:1-71', 'Exodus 16:1-36', 'Psalm 78:24-25']),
            $this->john(7, 'John 7 places Jesus at the Feast of Tabernacles in Jerusalem amid dispute over his identity.', 'Tabernacles celebrated wilderness dwelling, water, light, and harvest. Jesus speaks in that festival setting about his origin, mission, and living water, while crowds and leaders divide over him.', ['John 7:1-52', 'Leviticus 23:33-43', 'Zechariah 14:8']),
            $this->john(8, 'John 8 continues the Jerusalem controversies with themes of mercy, judgment, truth, freedom, and Abraham.', 'The chapter reflects debate over witness, sin, descent from Abraham, and Jesus\' divine identity. Its language of light and truth develops the Tabernacles setting and the Gospel\'s contrast between belief and rejection.', ['John 8:1-59', 'Genesis 15:6', 'Exodus 3:14']),
            $this->john(9, 'John 9 narrates the healing of a man born blind and the investigation that follows.', 'The story unfolds like a public hearing. The healed man grows in insight while the authorities become more resistant, turning physical sight and blindness into a historical and theological contrast.', ['John 9:1-41', 'Isaiah 35:5', 'Isaiah 42:7']),
            $this->john(10, 'John 10 presents Jesus as the door and the good shepherd, then returns to conflict at the Feast of Dedication.', 'Shepherd language recalls Israel\'s kings, leaders, and God as shepherd. The Dedication setting, connected with the temple\'s rededication after the Maccabean crisis, sharpens the question of Jesus\' consecration and identity.', ['John 10:1-42', 'Ezekiel 34:1-31', 'Psalm 23']),
            $this->john(11, 'John 11 tells of Lazarus\' death and raising at Bethany, leading to an intensified plot against Jesus.', 'Bethany lay near Jerusalem, making the sign both personal and public. The episode is the climactic sign before the Passion and shows how belief in Jesus as resurrection and life divides witnesses and authorities.', ['John 11:1-57', 'Daniel 12:2', 'Ezekiel 37:1-14']),
            $this->john(12, 'John 12 moves from Mary\'s anointing at Bethany to Jesus\' entry into Jerusalem and final public appeal.', 'The anointing anticipates burial, while the entry uses royal and Passover imagery. The mention of Greeks seeking Jesus signals the widening horizon of his mission as the hour of glorification approaches.', ['John 12:1-50', 'Zechariah 9:9', 'Isaiah 53:1']),
            $this->john(13, 'John 13 begins the farewell meal with the washing of the disciples\' feet, Judas\' departure, and the new commandment.', 'Unlike the Synoptics, John emphasizes symbolic action and extended farewell teaching at the final meal. Foot washing reflects humble service and prepares the disciples to understand love through the cross.', ['John 13:1-38', 'Exodus 12:1-14', 'Leviticus 19:18']),
            $this->john(14, 'John 14 records Jesus comforting the disciples, speaking of the Father\'s house, and promising the Paraclete.', 'The chapter belongs to the farewell discourse and addresses the disciples\' fear before Jesus\' departure. Its language of way, truth, life, indwelling, and Spirit prepares them for mission after Easter.', ['John 14:1-31', 'Deuteronomy 6:4', 'John 14:26']),
            $this->john(15, 'John 15 uses the image of the vine and branches and prepares the disciples for love, fruitfulness, and opposition.', 'The vine image draws on Israel as God\'s vineyard and recasts covenant identity around abiding in Jesus. The chapter moves from intimacy with Christ to realistic expectation of hostility from the world.', ['John 15:1-27', 'Isaiah 5:1-7', 'Psalm 80:8-19']),
            $this->john(16, 'John 16 continues the farewell discourse with teaching about persecution, the Spirit, sorrow, joy, and Jesus\' return to the Father.', 'The disciples are prepared for synagogue exclusion and grief, but also for the Spirit\'s guidance and the joy of Easter. The chapter reflects the pressures faced by early believers confessing Jesus.', ['John 16:1-33', 'Isaiah 66:7-14', 'John 16:13']),
            $this->john(17, 'John 17 presents Jesus\' prayer to the Father for his glorification, his disciples, and those who will believe through them.', 'Often called the high priestly prayer, the chapter brings together mission, consecration, unity, and divine love. It forms a bridge between the farewell discourse and the Passion.', ['John 17:1-26', 'Leviticus 16:1-34', 'Psalm 133:1']),
            $this->john(18, 'John 18 narrates Jesus\' arrest, appearances before Jewish authorities, Peter\'s denial, and the first stage before Pilate.', 'The Passion begins in a garden and moves through legal and political settings. John emphasizes Jesus\' composure and kingship while showing the pressure of Roman authority and local leadership.', ['John 18:1-40', 'Psalm 41:9', 'Isaiah 53:7']),
            $this->john(19, 'John 19 recounts Jesus before Pilate, the crucifixion, death, piercing of his side, and burial.', 'The chapter is rich in royal irony, Passover imagery, Scripture fulfillment, and eyewitness testimony. Details such as the seamless tunic, hyssop, and unbroken bones connect the Passion with Israel\'s worship and Scripture.', ['John 19:1-42', 'Exodus 12:46', 'Psalm 22:18', 'Zechariah 12:10']),
            $this->john(20, 'John 20 announces the empty tomb, the risen Jesus\' appearances, the gift of the Spirit, and the purpose of the Gospel.', 'The chapter moves from Mary Magdalene\'s early witness to the disciples\' Easter faith and Thomas\' confession. Its closing purpose statement explains the Gospel as written testimony for belief and life.', ['John 20:1-31', 'Psalm 118:22-24', 'John 20:30-31']),
            $this->john(21, 'John 21 adds a Galilean resurrection appearance, the miraculous catch, Peter\'s restoration, and the witness of the beloved disciple.', 'The lakeside scene recalls earlier fishing traditions and gives pastoral shape to Peter\'s role. The final verses anchor the Gospel in testimony while acknowledging that Jesus\' works exceed the written account.', ['John 21:1-25', 'Ezekiel 34:23', 'John 21:15-19']),
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
            'references' => $references,
        ];
    }
}
