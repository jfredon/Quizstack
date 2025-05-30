import {UserInterface} from "./user";

export interface CommentInterface {
    id: number;
    content: string;
    creationDate: Date;
    updateDate: Date;
    questionId: number;
    author: UserInterface;
}