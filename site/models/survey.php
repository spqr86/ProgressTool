<?php defined('_JEXEC') or die;

/**
 * (Site) Class ProgressToolModelSurvey
 *
 * Model for front-end survey functionality.
 *
 * @package ProgressTool
 * @subpackage site
 * @since 0.1.0
 *
 * @author  Morgan Nolan <morgan.nolan@hotmail.com>
 * @link    https://github.com/morghayn
 */
class ProgressToolModelSurvey extends JModelItem
{
    /**
     * Returns the countryID associated with countryString, else if not found returns 1 if not found.
     *
     * @param string $countryString the country name.
     * @return int the countryID.
     * @since 0.3.0
     */
    public function getCountryID($countryString)
    {
        $db = JFactory::getDbo();
        $getCountryID = $db->getQuery(true);

        $getCountryID
            ->select($db->quoteName('C.id'))
            ->from($db->quoteName('#__pt_country', 'C'))
            ->where($db->quoteName('C.country') . ' LIKE ' . $db->quote($countryString));

        $countryID = $db->setQuery($getCountryID)->loadResult();
        return is_null($countryID) ? 1 : $countryID;
    }

    /**
     * Retrieve a list of location specific questions.
     *
     * @param $countryID int country index used to get location specific questions.
     * @return object list comprising of the location specific questions.
     * @since 0.3.0
     */
    public function getQuestions($countryID)
    {
        $db = JFactory::getDbo();
        $getQuestions = $db->getQuery(true);

        $columns = array('Q.id', 'Q.question', 'CA.colour_hex', 'CA.colour_rgb');

        $getQuestions
            ->select($db->quoteName($columns))
            ->select('SUM(CH.weight) as total')
            ->from($db->quoteName('#__pt_question', 'Q'))
            ->innerjoin($db->quoteName('#__pt_question_country', 'CO') . ' ON ' . $db->quoteName('Q.id') . ' = ' . $db->quoteName('CO.question_id'))
            ->innerjoin($db->quoteName('#__pt_question_choice', 'CH') . ' ON ' . $db->quoteName('Q.id') . ' = ' . $db->quoteName('CH.question_id'))
            ->innerjoin($db->quoteName('#__pt_category', 'CA') . ' ON ' . $db->quoteName('Q.category_id') . ' = ' . $db->quoteName('CA.id'))
            ->where($db->quoteName('CO.country_id') . ' = ' . $db->quote($countryID))
            ->group($db->quoteName('Q.id'))
            ->order('Q.id ASC');

        return $db->setQuery($getQuestions)->loadObjectList();
    }

    /**
     * Retrieve a list of location specific choices. Additionally, to indicate whether a project has selected a choice, the project_id attribute has
     * is retrieved via a left join. If a selection has been made, the projectID will be present, else the field will return null.
     *
     * @param int $projectID project index for which selections will be retrieved
     * @param int $countryID country index used to get location specific choices.
     * @return array the choices grouped by their respective questions, with an attribute to indicate whether it has been selected or not.
     * @since 0.1.0
     */
    public function getChoices($projectID, $countryID)
    {
        $db = JFactory::getDbo();
        $choices = $db->getQuery(true);

        $columns = array('CH.id', 'CH.question_id', 'CH.choice', 'CH.weight', 'S.project_id');
        $leftJoinCondition1 = $db->quoteName('CH.id') . ' = ' . $db->quoteName('S.choice_id');
        $leftJoinCondition2 = $db->quoteName('S.project_id') . ' = ' . $db->quote($projectID);

        $choices
            ->select($db->quoteName($columns))
            ->from($db->quoteName('#__pt_question_choice', 'CH'))
            ->innerjoin($db->quoteName('#__pt_question', 'Q') . ' ON CH.question_id = Q.id')
            ->innerjoin($db->quoteName('#__pt_question_country', 'CO') . ' ON Q.id = CO.question_id')
            ->leftjoin($db->quoteName('#__pt_project_choice', 'S') . ' ON ' . $leftJoinCondition1 . ' AND ' . $leftJoinCondition2)
            ->where($db->quoteName('CO.country_id') . ' = ' . $db->quote($countryID));

        return $this->groupByQuestionID($db->setQuery($choices)->loadObjectList());
    }

    /**
     * Takes in choices through parameters and returns an array of the choices grouped by question.
     *
     * @param mixed $rows the choice rows which are to be grouped.
     * @return array the choices grouped by question.
     * @since 0.2.6
     */
    public function groupByQuestionID($rows)
    {
        $groupedChoices = array();

        foreach ($rows as $row)
        {
            // Grouping by questionID.
            $groupedChoices[$row->question_id][] = $row;
        }

        return $groupedChoices;
    }

    /**
     * TODO: Documentation here
     *
     * @param $projectID
     * @param $choiceID
     * @return array
     * @since 0.3.0
     */
    public function processSelection($projectID, $choiceID)
    {
        $db = JFactory::getDbo();
        $exists = $db->getQuery(true);
        $delete = $db->getQuery(true);
        $insert = $db->getQuery(true);
        $conditions = array($db->quoteName('project_id') . ' = ' . $db->quote($projectID), $db->quoteName('choice_id') . ' = ' . $db->quote($choiceID));

        $exists
            ->select('COUNT(*)')
            ->from($db->quoteName('#__pt_project_choice'))
            ->where($conditions)
            ->setLimit(1);

        if ($db->setQuery($exists)->loadResult()) // If selection exists, delete it.
        {
            $delete
                ->delete($db->quoteName('#__pt_project_choice'))
                ->where($conditions);
            $db->setQuery($delete)->execute();
            $active = false;
        }
        else // Else if selection does not exist, insert it.
        {
            $insert
                ->insert($db->quoteName('#__pt_project_choice'))
                ->columns($db->quoteName(array('project_id', 'choice_id')))
                ->values(implode(',', array($projectID, $choiceID)));
            $db->setQuery($insert)->execute();
            $active = true;
        }

        // Getting questionID.
        $getQuestionID = $db->getQuery(true);
        $getQuestionID
            ->select($db->quoteName('QC.question_id'))
            ->from($db->quoteName('#__pt_question_choice', 'QC'))
            ->where($db->quoteName('QC.id') . ' = ' . $db->quote($choiceID))
            ->setLimit(1);
        $questionID = $db->setQuery($getQuestionID)->loadResult();

        // Getting userScore.
        $getUserScore = $db->getQuery(true);
        $getUserScore
            ->select('IFNULL(SUM(QC.weight), 0) AS userScore')
            ->from($db->quoteName('#__pt_question_choice', 'QC'))
            ->innerjoin($db->quoteName('#__pt_project_choice', 'PC') . ' ON ' . $db->quoteName('QC.id') . ' = ' . $db->quoteName('PC.choice_id'))
            ->where($db->quoteName('QC.question_id') . ' = ' . $db->quote($questionID))
            ->where($db->quoteName('PC.project_id') . ' = ' . $db->quote($projectID))
            ->setLimit(1);
        $userScore = $db->setQuery($getUserScore)->loadResult();

        // Getting isComplete.
        $getIsComplete = $db->getQuery(true);
        $getIsComplete
            ->select('IF(SUM(QC.weight) = ' . $db->quote($userScore) . ', 1, 0) AS isComplete')
            ->from($db->quoteName('#__pt_question_choice', 'QC'))
            ->where($db->quoteName('QC.question_id') . ' = ' . $db->quote($questionID))
            ->setLimit(1);
        $isComplete = $db->setQuery($getIsComplete)->loadResult() == 1;


        return array("active" => $active, "questionID" => $questionID, "userScore" => $userScore, "isComplete" => $isComplete);
    }
}