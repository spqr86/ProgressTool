<?php defined('_JEXEC') or die; ?>
<input id="token"
       type="hidden"
       name="<?php echo JSession::getFormToken(); ?>"
       value="1"/>

<table>
    <thead>
        <tr>
            <th>Project ID</th>
            <th>User ID</th>
            <th>Project Name</th>
            <th>Creation Data</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($this->projects as $project): ?>
            <tr onclick="alert('TODO MODAL POP UP')// Open a modal with tools">
                <td><?php echo $project->id; ?></td>
                <td><?php echo $project->user_id; ?></td>
                <td><?php echo $project->name; ?></td>
                <td><?php echo $project->creation_date; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<!-- Put this in a modal -->
<!-- <button id="delete" onclick="deleteProject(<?php echo $project->id; ?>)">*</button> -->